<?php

namespace App\Domain\Notifications\Models;

use App\Domain\Membership\Models\Member;
use App\Domain\Notifications\Enums\NotificationCategory;
use App\Domain\Organisations\Models\Organisation;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property NotificationCategory $category
 * @property bool|null $in_app_enabled
 * @property bool|null $email_enabled
 * @property bool|null $sms_enabled
 * @property bool|null $messaging_enabled
 */
#[Fillable(['public_id', 'organisation_id', 'recipient_type', 'user_id', 'member_id', 'category', 'event_key', 'in_app_enabled', 'email_enabled', 'sms_enabled', 'messaging_enabled', 'digest_frequency', 'quiet_hours_start', 'quiet_hours_end', 'timezone', 'locale', 'preference_scope_key'])]
class NotificationPreference extends Model
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
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Member, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'category' => NotificationCategory::class,
            'in_app_enabled' => 'boolean',
            'email_enabled' => 'boolean',
            'sms_enabled' => 'boolean',
            'messaging_enabled' => 'boolean',
        ];
    }
}
