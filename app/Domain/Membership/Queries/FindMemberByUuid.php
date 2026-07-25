<?php

namespace App\Domain\Membership\Queries;

use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Services\MembershipBranchScope;
use App\Domain\Organisations\Tenancy\TenantContext;

class FindMemberByUuid
{
    public function __construct(
        private TenantContext $tenantContext,
        private MembershipBranchScope $branchScope,
    ) {}

    public function handle(string $uuid): ?Member
    {
        $query = Member::query()
            ->whereBelongsTo($this->tenantContext->organisation())
            ->with(['branch', 'user', 'groupMemberships.group'])
            ->where('public_id', $uuid);
        $this->branchScope->apply($query);

        return $query->first();
    }
}
