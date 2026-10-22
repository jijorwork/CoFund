<?php

namespace App\Domain\Membership\Actions;

use App\Domain\Membership\Enums\GroupMembershipStatus;
use App\Domain\Membership\Exceptions\InvalidMembershipOperation;
use App\Domain\Membership\Models\Group;
use App\Domain\Membership\Models\GroupMembership;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Services\MembershipBranchScope;
use App\Domain\Organisations\Tenancy\TenantContext;

class RemoveMemberFromGroup
{
    public function __construct(
        private TenantContext $tenantContext,
        private MembershipBranchScope $branchScope,
    ) {}

    public function handle(Group $group, Member $member): GroupMembership
    {
        $this->branchScope->assertAccessibleRecord($group->branch_id);

        if (
            $group->organisation_id !== $this->tenantContext->organisation()->getKey()
            || $member->organisation_id !== $this->tenantContext->organisation()->getKey()
        ) {
            throw new InvalidMembershipOperation;
        }

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
