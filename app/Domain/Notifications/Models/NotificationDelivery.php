<?php

namespace App\Domain\Notifications\Models;

use App\Domain\Notifications\Enums\NotificationChannel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property NotificationChannel $channel
 * @property int $attempt_count
 * @property Carbon|null $next_attempt_at
 * @property Carbon|null $queued_at
 * @property Carbon|null $sent_at
 * @property Carbon|null $delivered_at
 * @property Carbon|null $failed_at
 * @property Carbon|null $suppressed_at
 */
#[Fillable(['public_id', 'organisation_id', 'notification_dispatch_id', 'channel', 'destination_masked', 'provider', 'status', 'attempt_count', 'next_attempt_at', 'queued_at', 'sent_at', 'delivered_at', 'failed_at', 'suppressed_at', 'provider_message_reference', 'failure_code', 'failure_reason'])]
class NotificationDelivery extends Model
{
    use HasUuids;

    protected static function booted(): void
    {
        static::deleting(fn (): never => throw new \LogicException('Notification deliveries cannot be deleted.'));
    }

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    /** @return BelongsTo<NotificationDispatch, $this> */
    public function dispatch(): BelongsTo
    {
        return $this->belongsTo(NotificationDispatch::class, 'notification_dispatch_id');
    }

    /** @return HasMany<NotificationDeliveryAttempt, $this> */
    public function attempts(): HasMany
    {
        return $this->hasMany(NotificationDeliveryAttempt::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'channel' => NotificationChannel::class,
            'next_attempt_at' => 'datetime',
            'queued_at' => 'datetime',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'failed_at' => 'datetime',
            'suppressed_at' => 'datetime',
        ];
    }
}
