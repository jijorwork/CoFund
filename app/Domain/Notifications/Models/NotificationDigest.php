<?php

namespace App\Domain\Notifications\Models;

use App\Domain\Membership\Models\Member;
use App\Domain\Notifications\Enums\NotificationChannel;
use App\Domain\Organisations\Models\Organisation;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property NotificationChannel $channel
 * @property Carbon $period_start
 * @property Carbon $period_end
 * @property Carbon $scheduled_for
 * @property Carbon|null $generated_at
 * @property Carbon|null $sent_at
 * @property Carbon|null $failed_at
 */
#[Fillable(['public_id', 'organisation_id', 'recipient_type', 'recipient_uuid', 'user_id', 'member_id', 'frequency', 'channel', 'period_start', 'period_end', 'status', 'item_count', 'scheduled_for', 'generated_at', 'sent_at', 'failed_at'])]
class NotificationDigest extends Model
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

    /** @return HasMany<NotificationDigestItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(NotificationDigestItem::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'channel' => NotificationChannel::class,
            'period_start' => 'datetime',
            'period_end' => 'datetime',
            'scheduled_for' => 'datetime',
            'generated_at' => 'datetime',
            'sent_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }
}
