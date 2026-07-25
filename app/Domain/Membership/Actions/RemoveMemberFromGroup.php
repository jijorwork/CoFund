<?php

namespace App\Domain\Membership\Actions;

use App\Domain\Membership\Enums\GroupMembershipStatus;
use App\Domain\Membership\Exceptions\InvalidMembershipOperation;
use App\Domain\Membership\Models\Group;
use App\Domain\Membership\Models\GroupMembership;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Services\MembershipBranchScope;

class RemoveMemberFromGroup
{
    public function __construct(private MembershipBranchScope $branchScope) {}

    public function handle(Group $group, Member $member): GroupMembership
    {
        $this->branchScope->assertAccessibleRecord($group->branch_id);
        $membership = GroupMembership::query()
            ->whereBelongsTo($group)
            ->whereBelongsTo($member)
            ->where('status', GroupMembershipStatus::Active)
            ->first();

        if ($membership === null) {
            throw new InvalidMembershipOperation;
        }

        $membership->update([
            'status' => GroupMembershipStatus::Left,
            'left_at' => now()->toDateString(),
            'active_membership_key' => null,
        ]);

        return $membership->refresh();
    }
}
