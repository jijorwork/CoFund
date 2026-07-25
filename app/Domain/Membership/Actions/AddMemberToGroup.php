<?php

namespace App\Domain\Membership\Actions;

use App\Domain\Membership\Enums\GroupMembershipStatus;
use App\Domain\Membership\Exceptions\InvalidMembershipOperation;
use App\Domain\Membership\Models\Group;
use App\Domain\Membership\Models\GroupMembership;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Services\MembershipBranchScope;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AddMemberToGroup
{
    public function __construct(private MembershipBranchScope $branchScope) {}

    public function handle(Group $group, Member $member, User $actor): GroupMembership
    {
        $this->branchScope->assertAccessibleRecord($group->branch_id);
        $this->branchScope->assertAccessibleRecord($member->branch_id);

        if (
            $group->organisation_id !== $member->organisation_id
            || ($group->branch_id !== null && $member->branch_id !== $group->branch_id)
        ) {
            throw new InvalidMembershipOperation;
        }

        return DB::transaction(function () use ($group, $member, $actor): GroupMembership {
            $key = GroupMembership::activeMembershipKey($group, $member);

            if (GroupMembership::query()->where('active_membership_key', $key)->lockForUpdate()->exists()) {
                throw new InvalidMembershipOperation;
            }

            return GroupMembership::query()->create([
                'organisation_id' => $group->organisation_id,
                'group_id' => $group->getKey(),
                'member_id' => $member->getKey(),
                'joined_at' => now()->toDateString(),
                'status' => GroupMembershipStatus::Active,
                'assigned_by_user_id' => $actor->getKey(),
                'active_membership_key' => $key,
            ])->load(['group', 'member']);
        });
    }
}
