<?php

namespace App\Domain\Membership\Actions;

use App\Domain\Membership\Models\Group;
use App\Domain\Membership\Services\MembershipBranchScope;
use App\Domain\Organisations\Models\Branch;

class UpdateGroup
{
    public function __construct(private MembershipBranchScope $branchScope) {}

    /** @param array<string, mixed> $attributes */
    public function handle(Group $group, array $attributes, ?Branch $branch): Group
    {
        $this->branchScope->assertAccessibleRecord($group->branch_id);
        $this->branchScope->assertAccessibleBranch($branch);
        $group->update([...$attributes, 'branch_id' => $branch?->getKey()]);

        return $group->refresh()->load('branch');
    }
}
