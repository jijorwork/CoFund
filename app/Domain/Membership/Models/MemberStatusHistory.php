<?php

namespace App\Domain\Membership\Models;

use App\Domain\Identity\Models\Role;
use App\Domain\Membership\Enums\MemberStatus;
use App\Domain\Organisations\Models\Organisation;
use App\Models\User;
use Database\Factories\Domain\Membership\Models\MemberStatusHistoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'public_id', 'organisation_id', 'member_id', 'from_status', 'to_status',
    'reason', 'effective_at', 'changed_by_user_id', 'acting_role_id', 'metadata',
])]
class MemberStatusHistory extends Model
{
    /** @use HasFactory<MemberStatusHistoryFactory> */
    use HasFactory, HasUuids;

    public const UPDATED_AT = null;

    protected static function newFactory(): MemberStatusHistoryFactory
    {
        return MemberStatusHistoryFactory::new();
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Member status history is append-only.'));
        static::deleting(fn () => throw new \LogicException('Member status history is append-only.'));
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

    /** @return BelongsTo<Member, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /** @return BelongsTo<User, $this> */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }

    /** @return BelongsTo<Role, $this> */
    public function actingRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'acting_role_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'from_status' => MemberStatus::class,
            'to_status' => MemberStatus::class,
            'effective_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
