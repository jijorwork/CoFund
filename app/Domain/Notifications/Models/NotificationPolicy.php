<?php

namespace App\Domain\Notifications\Models;

use App\Domain\Notifications\Enums\NotificationCategory;
use App\Domain\Notifications\Enums\NotificationPriority;
use App\Domain\Organisations\Models\Organisation;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property NotificationCategory $category
 * @property NotificationPriority $priority
 * @property bool $enabled
 * @property bool|null $mandatory_override
 * @property bool $in_app_enabled
 * @property bool $email_enabled
 * @property bool $sms_enabled
 * @property bool $messaging_enabled
 */
#[Fillable(['public_id', 'organisation_id', 'event_key', 'category', 'enabled', 'mandatory_override', 'priority', 'in_app_enabled', 'email_enabled', 'sms_enabled', 'messaging_enabled', 'recipient_policy', 'digest_mode', 'quiet_hours_policy', 'created_by_user_id', 'updated_by_user_id'])]
class NotificationPolicy extends Model
{
    use HasUuids;

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

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'category' => NotificationCategory::class,
            'priority' => NotificationPriority::class,
            'enabled' => 'boolean',
            'mandatory_override' => 'boolean',
            'in_app_enabled' => 'boolean',
            'email_enabled' => 'boolean',
            'sms_enabled' => 'boolean',
            'messaging_enabled' => 'boolean',
            'recipient_policy' => 'array',
            'quiet_hours_policy' => 'array',
        ];
    }
}
