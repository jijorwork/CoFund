<?php

namespace App\Domain\Notifications\Models;

use App\Domain\Notifications\Enums\NotificationCategory;
use App\Domain\Notifications\Enums\NotificationPriority;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property NotificationCategory $category
 * @property NotificationPriority $priority
 * @property bool $mandatory
 * @property Carbon|null $read_at
 * @property Carbon|null $archived_at
 */
#[Fillable(['public_id', 'organisation_id', 'notification_dispatch_id', 'user_id', 'event_key', 'category', 'priority', 'title', 'body', 'action_label', 'action_url', 'mandatory', 'read_at', 'archived_at'])]
class InAppNotification extends Model
{
    use HasUuids;

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

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'category' => NotificationCategory::class,
            'priority' => NotificationPriority::class,
            'mandatory' => 'boolean',
            'read_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }
}
