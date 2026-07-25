<?php

namespace App\Domain\Membership\Actions;

use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Services\MembershipBranchScope;

class UnlinkMemberFromUser
{
    public function __construct(private MembershipBranchScope $branchScope) {}

    public function handle(Member $member): Member
    {
        $this->branchScope->assertAccessibleRecord($member->branch_id);
        $member->update(['user_id' => null, 'active_user_link_key' => null]);

        return $member->refresh()->load(['branch', 'user']);
    }
}
