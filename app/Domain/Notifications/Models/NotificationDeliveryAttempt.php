<?php

namespace App\Domain\Notifications\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon $started_at
 * @property Carbon|null $completed_at
 */
#[Fillable(['public_id', 'organisation_id', 'notification_delivery_id', 'attempt_number', 'started_at', 'completed_at', 'status', 'provider_request_reference', 'provider_response_code', 'failure_code', 'failure_reason', 'duration_ms'])]
class NotificationDeliveryAttempt extends Model
{
    public const UPDATED_AT = null;

    use HasUuids;

    protected static function booted(): void
    {
        static::updating(function (self $attempt): void {
            $allowed = [
                'completed_at', 'status', 'provider_response_code',
                'failure_code', 'failure_reason', 'duration_ms',
            ];

            if (
                $attempt->getRawOriginal('status') !== 'started'
                || $attempt->getRawOriginal('completed_at') !== null
                || ! in_array($attempt->status, ['succeeded', 'failed', 'timed_out'], true)
                || $attempt->completed_at === null
                || array_diff(array_keys($attempt->getDirty()), $allowed) !== []
            ) {
                throw new \LogicException('Finalized notification delivery attempts are immutable.');
            }
        });
        static::deleting(fn (): never => throw new \LogicException('Notification delivery attempts cannot be deleted.'));
    }

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    /** @return BelongsTo<NotificationDelivery, $this> */
    public function delivery(): BelongsTo
    {
        return $this->belongsTo(NotificationDelivery::class, 'notification_delivery_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'completed_at' => 'datetime'];
    }
}
