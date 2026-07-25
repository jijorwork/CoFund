<?php

namespace App\Domain\Membership\Services;

use App\Domain\Membership\Exceptions\InvalidMembershipOperation;
use App\Domain\Organisations\Models\Branch;
use App\Domain\Organisations\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class MembershipBranchScope
{
    public function __construct(private TenantContext $tenantContext) {}

    public function isBranchRestricted(): bool
    {
        return $this->tenantContext->roleAssignment()->branch_id !== null;
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function apply(Builder $query): Builder
    {
        if ($this->isBranchRestricted()) {
            $query->where('branch_id', $this->tenantContext->branch()?->getKey());
        }

        return $query;
    }

    public function assertAccessibleBranch(?Branch $branch): void
    {
        if ($branch !== null && $branch->organisation_id !== $this->tenantContext->organisation()->getKey()) {
            throw new InvalidMembershipOperation;
        }

        if (
            $this->isBranchRestricted()
            && $branch?->getKey() !== $this->tenantContext->branch()?->getKey()
        ) {
            throw new InvalidMembershipOperation;
        }
    }

    public function assertAccessibleRecord(?int $branchId): void
    {
        if (
            $this->isBranchRestricted()
            && $branchId !== $this->tenantContext->branch()?->getKey()
        ) {
            throw new InvalidMembershipOperation;
        }
    }
}
