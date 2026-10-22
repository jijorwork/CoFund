<?php

namespace App\Domain\Notifications\Services;

use App\Domain\Notifications\Contracts\MessagingNotificationProvider;
use App\Domain\Notifications\Contracts\SmsNotificationProvider;
use App\Domain\Notifications\Enums\NotificationChannel;
use App\Domain\Notifications\Models\InAppNotification;
use App\Domain\Notifications\Models\NotificationDelivery;
use App\Domain\Notifications\Models\NotificationDeliveryAttempt;
use App\Domain\Notifications\Models\NotificationDispatch;
use App\Domain\Notifications\ValueObjects\MessagingMessage;
use App\Domain\Notifications\ValueObjects\ProviderDeliveryResult;
use App\Domain\Notifications\ValueObjects\SmsMessage;
use App\Mail\NotificationEmail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

class DeliverNotificationChannel
{
    public function __construct(
        private readonly SmsNotificationProvider $sms,
        private readonly MessagingNotificationProvider $messaging,
    ) {}

    public function handle(string $deliveryUuid, NotificationChannel $expectedChannel): void
    {
        $delivery = $this->startAttempt($deliveryUuid, $expectedChannel);

        if ($delivery === null) {
            return;
        }

        $startedAt = hrtime(true);

        try {
            $result = $this->send($delivery, $expectedChannel);
        } catch (Throwable) {
            $result = new ProviderDeliveryResult(
                successful: false,
                failureCode: 'provider_error',
                failureReason: 'The provider could not complete delivery.',
                retryable: true,
            );
        }

        $this->completeAttempt($delivery, $result, (int) ((hrtime(true) - $startedAt) / 1_000_000));

        if (! $result->successful && $delivery->refresh()->status === 'retry_pending') {
            throw new RuntimeException('Temporary notification delivery failure.');
        }
    }

    private function startAttempt(string $deliveryUuid, NotificationChannel $expectedChannel): ?NotificationDelivery
    {
        return DB::transaction(function () use ($deliveryUuid, $expectedChannel): ?NotificationDelivery {
            $delivery = NotificationDelivery::query()
                ->with(['dispatch.user'])
                ->where('public_id', $deliveryUuid)
                ->lockForUpdate()
                ->firstOrFail();

            if ($delivery->channel !== $expectedChannel || in_array($delivery->status, ['delivered', 'sent', 'suppressed', 'unavailable', 'permanently_failed'], true)) {
                return null;
            }

            if ($delivery->status === 'sending') {
                $previous = $delivery->attempts()
                    ->where('attempt_number', $delivery->attempt_count)
                    ->lockForUpdate()
                    ->first();

                if ($previous !== null && $previous->started_at->greaterThan(now()->subMinutes(5))) {
                    throw new RuntimeException('Notification delivery is already in progress.');
                }

                if ($previous !== null) {
                    $previous->update([
                        'completed_at' => now(),
                        'status' => 'timed_out',
                        'failure_code' => 'delivery_timeout',
                        'failure_reason' => 'The previous delivery attempt did not complete.',
                    ]);
                }

                if ($delivery->attempt_count >= $this->maximumAttempts($delivery->channel)) {
                    $delivery->update([
                        'status' => 'permanently_failed',
                        'failed_at' => now(),
                        'failure_code' => 'delivery_timeout',
                        'failure_reason' => 'The previous delivery attempt did not complete.',
                    ]);
                    $this->finishDispatchIfDone($delivery->dispatch()->lockForUpdate()->firstOrFail());

                    return null;
                }
            }

            if ($delivery->status === 'retry_pending' && $delivery->next_attempt_at?->isFuture()) {
                throw new RuntimeException('Notification delivery is not ready for retry.');
            }

            $attemptNumber = $delivery->attempt_count + 1;
            $delivery->update([
                'status' => 'sending',
                'attempt_count' => $attemptNumber,
                'queued_at' => $delivery->queued_at ?? now(),
            ]);
            NotificationDeliveryAttempt::query()->create([
                'organisation_id' => $delivery->organisation_id,
                'notification_delivery_id' => $delivery->getKey(),
                'attempt_number' => $attemptNumber,
                'started_at' => now(),
                'status' => 'started',
            ]);

            return $delivery;
        });
    }

    private function send(NotificationDelivery $delivery, NotificationChannel $channel): ProviderDeliveryResult
    {
        $dispatch = $delivery->dispatch;

        return match ($channel) {
            NotificationChannel::InApp => $this->deliverInApp($delivery),
            NotificationChannel::Email => $this->deliverEmail($delivery),
            NotificationChannel::Sms => $this->sms->send(new SmsMessage('', $dispatch->body)),
            NotificationChannel::Messaging => $this->messaging->send(new MessagingMessage('', $dispatch->body)),
        };
    }

    private function deliverInApp(NotificationDelivery $delivery): ProviderDeliveryResult
    {
        $dispatch = $delivery->dispatch;

        InAppNotification::query()->firstOrCreate(
            ['notification_dispatch_id' => $dispatch->getKey()],
            [
                'organisation_id' => $dispatch->organisation_id,
                'user_id' => $dispatch->user_id,
                'event_key' => $dispatch->event_key,
                'category' => $dispatch->category,
                'priority' => $dispatch->priority,
                'title' => $dispatch->title ?? 'Notification',
                'body' => $dispatch->body,
                'action_label' => $dispatch->action_label,
                'action_url' => $dispatch->action_url,
                'mandatory' => $dispatch->mandatory,
            ],
        );

        return new ProviderDeliveryResult(true);
    }

    private function deliverEmail(NotificationDelivery $delivery): ProviderDeliveryResult
    {
        $dispatch = $delivery->dispatch;
        $email = $dispatch->user->email;

        if ($dispatch->user->email_verified_at === null) {
            return new ProviderDeliveryResult(false, failureCode: 'invalid_destination', failureReason: 'No verified email address is available.');
        }

        Mail::to($email)->send(new NotificationEmail(
            $dispatch->title ?? (string) config('app.name'),
            $dispatch->body,
        ));

        return new ProviderDeliveryResult(true);
    }

    private function completeAttempt(NotificationDelivery $delivery, ProviderDeliveryResult $result, int $durationMs): void
    {
        DB::transaction(function () use ($delivery, $result, $durationMs): void {
            /** @var NotificationDelivery $locked */
            $locked = NotificationDelivery::query()->lockForUpdate()->findOrFail($delivery->getKey());
            $attempt = NotificationDeliveryAttempt::query()
                ->where('notification_delivery_id', $locked->getKey())
                ->where('attempt_number', $locked->attempt_count)
                ->firstOrFail();
            $maximumAttempts = $this->maximumAttempts($locked->channel);
            $permanent = ! $result->successful && (! $result->retryable || $locked->attempt_count >= $maximumAttempts);

            $attempt->update([
                'completed_at' => now(),
                'status' => $result->successful ? 'succeeded' : 'failed',
                'provider_response_code' => $result->responseCode,
                'failure_code' => $result->failureCode,
                'failure_reason' => $result->failureReason,
                'duration_ms' => $durationMs,
            ]);

            $locked->update([
                'status' => $result->successful ? 'delivered' : ($permanent ? 'permanently_failed' : 'retry_pending'),
                'sent_at' => $result->successful ? now() : null,
                'delivered_at' => $result->successful ? now() : null,
                'failed_at' => $permanent ? now() : null,
                'next_attempt_at' => ! $result->successful && ! $permanent
                    ? now()->addSeconds($this->retryDelaySeconds($locked->channel, $locked->attempt_count))
                    : null,
                'provider_message_reference' => $result->providerReference,
                'failure_code' => $result->failureCode,
                'failure_reason' => $result->failureReason,
            ]);

            $this->finishDispatchIfDone($locked->dispatch()->lockForUpdate()->firstOrFail());
        });
    }

    private function finishDispatchIfDone(NotificationDispatch $dispatch): void
    {
        if ($dispatch->deliveries()->whereIn('status', ['pending', 'sending', 'retry_pending'])->exists()) {
            return;
        }

        $hasSuccess = $dispatch->deliveries()->where('status', 'delivered')->exists();
        $dispatch->update([
            'status' => $hasSuccess ? 'completed' : 'failed',
            'completed_at' => $hasSuccess ? now() : null,
            'failed_at' => $hasSuccess ? null : now(),
        ]);
    }

    private function maximumAttempts(NotificationChannel $channel): int
    {
        return $channel === NotificationChannel::Email ? 5 : 3;
    }

    private function retryDelaySeconds(NotificationChannel $channel, int $attemptNumber): int
    {
        $delays = $channel === NotificationChannel::Email
            ? [60, 300, 900, 3600]
            : [60, 300];

        return $delays[min($attemptNumber - 1, count($delays) - 1)];
    }
}
