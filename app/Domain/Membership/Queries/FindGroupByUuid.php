<?php

namespace App\Domain\Membership\Queries;

use App\Domain\Membership\Models\Group;
use App\Domain\Membership\Services\MembershipBranchScope;
use App\Domain\Organisations\Tenancy\TenantContext;

class FindGroupByUuid
{
    public function __construct(
        private TenantContext $tenantContext,
        private MembershipBranchScope $branchScope,
    ) {}

    public function handle(string $uuid): ?Group
    {
        $query = Group::query()
            ->whereBelongsTo($this->tenantContext->organisation())
            ->with('branch')
            ->where('public_id', $uuid);
        $this->branchScope->apply($query);

        return $query->first();
    }
}
