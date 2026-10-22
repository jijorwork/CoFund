<?php

namespace App\Domain\Notifications\Actions;

use App\Domain\Notifications\Enums\NotificationChannel;
use App\Domain\Notifications\Models\NotificationDelivery;
use App\Domain\Notifications\Models\NotificationDigest;
use App\Domain\Notifications\Models\NotificationDigestItem;
use App\Domain\Notifications\Models\NotificationDispatch;
use App\Mail\NotificationEmail;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class SendDueNotificationDigests
{
    public function handle(): int
    {
        $this->collectPending();
        $sent = 0;

        NotificationDigest::query()->where('status', 'pending')
            ->where('scheduled_for', '<=', now())
            ->with(['user', 'items.dispatch'])
            ->chunkById(50, function ($digests) use (&$sent): void {
                foreach ($digests as $digest) {
                    if ($digest->user === null || $digest->items->isEmpty() || $digest->user->email_verified_at === null) {
                        DB::transaction(function () use ($digest): void {
                            foreach ($digest->items()->with('dispatch.deliveries')->get() as $item) {
                                $delivery = $item->dispatch->deliveries->firstWhere('channel', NotificationChannel::Email);
                                $delivery?->update(['status' => 'unavailable', 'failed_at' => now(), 'failure_code' => 'invalid_destination']);
                                $this->finishDispatch($item->dispatch);
                            }
                            $digest->update(['status' => 'failed', 'failed_at' => now()]);
                        });

                        continue;
                    }

                    $body = $digest->items->sortBy('sequence_number')->map(fn (NotificationDigestItem $item): string => '• '.$item->dispatch->title.' — '.$item->dispatch->body)->implode("\n");
                    Mail::to($digest->user->email)->send(new NotificationEmail('CoFund notification digest', $body));

                    DB::transaction(function () use ($digest): void {
                        $locked = NotificationDigest::query()->whereKey($digest->getKey())->lockForUpdate()->firstOrFail();
                        if ($locked->status !== 'pending') {
                            return;
                        }

                        foreach ($locked->items()->with('dispatch.deliveries')->orderBy('sequence_number')->get() as $item) {
                            $delivery = $item->dispatch->deliveries->firstWhere('channel', NotificationChannel::Email);
                            if ($delivery?->status === 'digest_queued') {
                                $delivery->update(['status' => 'delivered', 'sent_at' => now(), 'delivered_at' => now()]);
                                $this->finishDispatch($item->dispatch);
                            }
                        }

                        $locked->update(['status' => 'sent', 'sent_at' => now()]);
                    });
                    $sent++;
                }
            });

        return $sent;
    }

    private function finishDispatch(NotificationDispatch $dispatch): void
    {
        if ($dispatch->deliveries()->whereIn('status', ['pending', 'sending', 'retry_pending', 'digest_pending', 'digest_queued'])->exists()) {
            return;
        }

        $successful = $dispatch->deliveries()->whereIn('status', ['delivered', 'sent'])->exists();
        $dispatch->update([
            'status' => $successful ? 'completed' : 'failed',
            'completed_at' => $successful ? now() : null,
            'failed_at' => $successful ? null : now(),
        ]);
    }

    private function collectPending(): void
    {
        NotificationDelivery::query()->where('status', 'digest_pending')
            ->with(['dispatch.organisation', 'dispatch.user'])
            ->chunkById(100, function ($deliveries): void {
                foreach ($deliveries as $delivery) {
                    $dispatch = $delivery->dispatch;
                    $frequency = $dispatch->policy_snapshot['digest_frequency'] ?? null;
                    if (! in_array($frequency, ['hourly', 'daily', 'weekly'], true) || $dispatch->user === null) {
                        continue;
                    }

                    $local = CarbonImmutable::instance($dispatch->created_at)->setTimezone($dispatch->timezone);
                    $start = match ($frequency) {
                        'hourly' => $local->startOfHour(),
                        'daily' => $local->startOfDay(),
                        'weekly' => $local->startOfWeek(),
                    };
                    $end = match ($frequency) {
                        'hourly' => $start->addHour(),
                        'daily' => $start->addDay(),
                        'weekly' => $start->addWeek(),
                    };

                    DB::transaction(function () use ($delivery, $dispatch, $frequency, $start, $end): void {
                        $lockedDelivery = NotificationDelivery::query()->whereKey($delivery->getKey())->lockForUpdate()->firstOrFail();
                        if ($lockedDelivery->status !== 'digest_pending') {
                            return;
                        }

                        $digest = NotificationDigest::query()->firstOrCreate([
                            'organisation_id' => $dispatch->organisation_id,
                            'recipient_type' => 'user',
                            'recipient_uuid' => $dispatch->recipient_uuid,
                            'frequency' => $frequency,
                            'channel' => 'email',
                            'period_start' => $start->utc(),
                        ], [
                            'user_id' => $dispatch->user_id,
                            'period_end' => $end->utc(),
                            'scheduled_for' => $end->utc(),
                        ]);
                        NotificationDigestItem::query()->firstOrCreate([
                            'notification_dispatch_id' => $dispatch->getKey(),
                        ], [
                            'organisation_id' => $dispatch->organisation_id,
                            'notification_digest_id' => $digest->getKey(),
                            'sequence_number' => $digest->items()->count() + 1,
                        ]);
                        $digest->update(['item_count' => $digest->items()->count(), 'generated_at' => now()]);
                        $lockedDelivery->update(['status' => 'digest_queued']);
                    });
                }
            });
    }
}
