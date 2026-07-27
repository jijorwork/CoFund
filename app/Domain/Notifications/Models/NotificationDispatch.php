<?php

namespace App\Domain\Notifications\Models;

use App\Domain\Notifications\Enums\NotificationCategory;
use App\Domain\Notifications\Enums\NotificationPriority;
use App\Domain\Organisations\Models\Branch;
use App\Domain\Organisations\Models\Organisation;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property NotificationCategory $category
 * @property NotificationPriority $priority
 * @property array<string, mixed> $policy_snapshot
 * @property array<string, mixed>|null $context_snapshot
 * @property Carbon|null $scheduled_for
 * @property Carbon|null $dispatched_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $failed_at
 */
#[Fillable(['public_id', 'organisation_id', 'branch_id', 'event_key', 'category', 'priority', 'source_type', 'source_uuid', 'recipient_type', 'recipient_uuid', 'user_id', 'member_id', 'locale', 'timezone', 'title', 'body', 'action_label', 'action_url', 'template_id', 'template_version', 'policy_snapshot', 'context_snapshot', 'status', 'mandatory', 'digestible', 'deduplication_key', 'scheduled_for', 'dispatched_at', 'completed_at', 'failed_at'])]
class NotificationDispatch extends Model
{
    use HasUuids;

    protected static function booted(): void
    {
        static::deleting(fn (): never => throw new \LogicException('Notification dispatches cannot be deleted.'));
    }

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    /** @return BelongsTo<Organisation, $this> */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<NotificationTemplate, $this> */
    public function template(): BelongsTo
    {
        return $this->belongsTo(NotificationTemplate::class);
    }

    /** @return HasMany<NotificationDelivery, $this> */
    public function deliveries(): HasMany
    {
        return $this->hasMany(NotificationDelivery::class);
    }

    /** @return HasOne<InAppNotification, $this> */
    public function inAppNotification(): HasOne
    {
        return $this->hasOne(InAppNotification::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'category' => NotificationCategory::class,
            'priority' => NotificationPriority::class,
            'policy_snapshot' => 'array',
            'context_snapshot' => 'array',
            'mandatory' => 'boolean',
            'digestible' => 'boolean',
            'scheduled_for' => 'datetime',
            'dispatched_at' => 'datetime',
            'completed_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }
}
