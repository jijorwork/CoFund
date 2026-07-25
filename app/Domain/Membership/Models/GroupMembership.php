<?php

namespace App\Domain\Membership\Models;

use App\Domain\Membership\Enums\GroupMembershipStatus;
use App\Domain\Organisations\Models\Organisation;
use App\Models\User;
use Database\Factories\Domain\Membership\Models\GroupMembershipFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property GroupMembershipStatus $status
 */
#[Fillable([
    'public_id', 'organisation_id', 'group_id', 'member_id', 'joined_at',
    'left_at', 'status', 'assigned_by_user_id', 'active_membership_key',
])]
class GroupMembership extends Model
{
    /** @use HasFactory<GroupMembershipFactory> */
    use HasFactory, HasUuids;

    protected $attributes = ['status' => 'active'];

    protected static function newFactory(): GroupMembershipFactory
    {
        return GroupMembershipFactory::new();
    }

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public static function activeMembershipKey(Group $group, Member $member): string
    {
        return $group->getKey().':'.$member->getKey();
    }

    /** @return BelongsTo<Organisation, $this> */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    /** @return BelongsTo<Group, $this> */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /** @return BelongsTo<Member, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /** @return BelongsTo<User, $this> */
    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_user_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => GroupMembershipStatus::class,
            'joined_at' => 'date',
            'left_at' => 'date',
        ];
    }
}
