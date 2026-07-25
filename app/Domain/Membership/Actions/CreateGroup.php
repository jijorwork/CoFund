<?php

namespace App\Domain\Membership\Actions;

use App\Domain\Identity\Auditing\ActingContext;
use App\Domain\Membership\Models\Group;
use App\Domain\Membership\Services\MembershipBranchScope;
use App\Domain\Organisations\Models\Branch;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Models\User;

class CreateGroup
{
    public function __construct(
        private TenantContext $tenantContext,
        private MembershipBranchScope $branchScope,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function handle(User $actor, array $attributes, ?Branch $branch): Group
    {
        $this->branchScope->assertAccessibleBranch($branch);
        $acting = ActingContext::from($actor, $this->tenantContext);

        return Group::query()->create([
            ...$attributes,
            'organisation_id' => $acting->organisationId,
            'branch_id' => $branch?->getKey(),
            'created_by_user_id' => $acting->userId,
        ])->load('branch');
    }
}
