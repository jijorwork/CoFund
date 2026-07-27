<?php

namespace App\Domain\Notifications\Actions;

use App\Domain\Notifications\Enums\NotificationChannel;
use App\Domain\Notifications\Models\NotificationDelivery;
use App\Domain\Notifications\Models\NotificationDispatch;
use App\Domain\Notifications\Models\NotificationPolicy;
use App\Domain\Notifications\Models\NotificationPreference;
use App\Domain\Notifications\Services\BuildNotificationDeduplicationKey;
use App\Domain\Notifications\Services\NotificationEventRegistry;
use App\Domain\Notifications\ValueObjects\NotificationDispatchData;
use App\Jobs\DeliverEmailNotification;
use App\Jobs\DeliverInAppNotification;
use App\Jobs\DeliverMessagingNotification;
use App\Jobs\DeliverSmsNotification;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class CreateNotificationDispatch
{
    public function __construct(
        private readonly NotificationEventRegistry $events,
        private readonly BuildNotificationDeduplicationKey $deduplication,
    ) {}

    public function handle(NotificationDispatchData $data): NotificationDispatch
    {
        $definition = $this->events->get($data->eventKey);
        $policy = NotificationPolicy::query()
            ->whereBelongsTo($data->organisation)
            ->where('event_key', $data->eventKey)
            ->first();
        $mandatory = $definition->mandatory || $policy?->mandatory_override === true;
        $channels = $this->channels($data, $policy, $mandatory);
        $deduplicationKey = $this->deduplication->build(
            (string) $data->organisation->public_id,
            $data->eventKey,
            $data->sourceUuid,
            'user',
            (string) $data->recipient->public_id,
            $data->eventKey,
        );

        try {
            return DB::transaction(function () use ($data, $definition, $policy, $mandatory, $channels, $deduplicationKey): NotificationDispatch {
                $dispatch = NotificationDispatch::query()->create([
                    'organisation_id' => $data->organisation->getKey(),
                    'branch_id' => $data->branch?->getKey(),
                    'event_key' => $definition->key,
                    'category' => $definition->category,
                    'priority' => $policy instanceof NotificationPolicy ? $policy->priority : $definition->priority,
                    'source_type' => $data->sourceType,
                    'source_uuid' => $data->sourceUuid,
                    'recipient_type' => 'user',
                    'recipient_uuid' => $data->recipient->public_id,
                    'user_id' => $data->recipient->getKey(),
                    'locale' => (string) config('app.locale'),
                    'timezone' => $data->organisation->timezone,
                    'title' => $data->title,
                    'body' => $data->body,
                    'action_label' => $data->actionLabel,
                    'action_url' => $data->actionUrl,
                    'policy_snapshot' => [
                        'policy_uuid' => $policy?->public_id,
                        'mandatory' => $mandatory,
                    ],
                    'context_snapshot' => $data->context,
                    'status' => $channels === [] ? 'failed' : 'pending',
                    'mandatory' => $mandatory,
                    'digestible' => $definition->digestible,
                    'deduplication_key' => $deduplicationKey,
                    'failed_at' => $channels === [] ? now() : null,
                ]);

                foreach ($channels as $channel) {
                    $delivery = NotificationDelivery::query()->create([
                        'organisation_id' => $data->organisation->getKey(),
                        'notification_dispatch_id' => $dispatch->getKey(),
                        'channel' => $channel,
                        'destination_masked' => $this->maskedDestination($channel, $data),
                        'status' => $this->isAvailable($channel, $data) ? 'pending' : 'unavailable',
                        'failure_code' => $this->isAvailable($channel, $data) ? null : 'channel_unavailable',
                        'failure_reason' => $this->isAvailable($channel, $data) ? null : 'No verified destination or provider is configured.',
                    ]);

                    if ($this->isAvailable($channel, $data)) {
                        DB::afterCommit(fn () => $this->queue($channel, (string) $delivery->public_id));
                    }
                }

                return $dispatch->load('deliveries');
            });
        } catch (QueryException $exception) {
            if ($exception->getCode() !== '23000') {
                throw $exception;
            }

            return NotificationDispatch::query()
                ->where('organisation_id', $data->organisation->getKey())
                ->where('deduplication_key', $deduplicationKey)
                ->firstOrFail();
        }
    }

    /** @return list<NotificationChannel> */
    private function channels(NotificationDispatchData $data, ?NotificationPolicy $policy, bool $mandatory): array
    {
        $definition = $this->events->get($data->eventKey);

        if ($policy?->enabled === false && ! $mandatory) {
            return [];
        }

        $requested = $data->channels ?? $definition->defaultChannels;
        $preference = NotificationPreference::query()
            ->whereBelongsTo($data->organisation)
            ->where('recipient_type', 'user')
            ->where('user_id', $data->recipient->getKey())
            ->where('category', $definition->category->value)
            ->where(fn ($query) => $query->whereNull('event_key')->orWhere('event_key', $data->eventKey))
            ->orderByRaw('event_key is not null desc')
            ->first();

        return array_values(array_filter($requested, function (NotificationChannel $channel) use ($policy, $preference, $mandatory): bool {
            if ($mandatory) {
                return true;
            }

            $enabled = $this->channelEnabled($channel, $preference, $policy);

            return $enabled ?? $channel === NotificationChannel::InApp;
        }));
    }

    private function channelEnabled(
        NotificationChannel $channel,
        ?NotificationPreference $preference,
        ?NotificationPolicy $policy,
    ): ?bool {
        $preferred = $preference instanceof NotificationPreference
            ? match ($channel) {
                NotificationChannel::InApp => $preference->in_app_enabled,
                NotificationChannel::Email => $preference->email_enabled,
                NotificationChannel::Sms => $preference->sms_enabled,
                NotificationChannel::Messaging => $preference->messaging_enabled,
            }
        : null;

        if ($preferred !== null || ! $policy instanceof NotificationPolicy) {
            return $preferred;
        }

        return match ($channel) {
            NotificationChannel::InApp => $policy->in_app_enabled,
            NotificationChannel::Email => $policy->email_enabled,
            NotificationChannel::Sms => $policy->sms_enabled,
            NotificationChannel::Messaging => $policy->messaging_enabled,
        };
    }

    private function isAvailable(NotificationChannel $channel, NotificationDispatchData $data): bool
    {
        return match ($channel) {
            NotificationChannel::InApp => true,
            NotificationChannel::Email => $data->recipient->email_verified_at !== null,
            NotificationChannel::Sms, NotificationChannel::Messaging => false,
        };
    }

    private function maskedDestination(NotificationChannel $channel, NotificationDispatchData $data): ?string
    {
        if ($channel !== NotificationChannel::Email) {
            return null;
        }

        [$name, $domain] = explode('@', $data->recipient->email, 2);

        return mb_substr($name, 0, 1).'***@'.$domain;
    }

    private function queue(NotificationChannel $channel, string $deliveryUuid): void
    {
        match ($channel) {
            NotificationChannel::InApp => DeliverInAppNotification::dispatch($deliveryUuid),
            NotificationChannel::Email => DeliverEmailNotification::dispatch($deliveryUuid),
            NotificationChannel::Sms => DeliverSmsNotification::dispatch($deliveryUuid),
            NotificationChannel::Messaging => DeliverMessagingNotification::dispatch($deliveryUuid),
        };
    }
}
