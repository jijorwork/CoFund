<?php

namespace App\Domain\Contributions\Services;

use App\Domain\Contributions\Exceptions\InvalidContributionOperation;
use App\Domain\Membership\Models\Group;
use App\Domain\Organisations\Models\Branch;
use App\Domain\Organisations\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ContributionScope
{
    public function __construct(private TenantContext $context) {}

    public function isBranchRestricted(): bool
    {
        return $this->context->roleAssignment()->branch_id !== null;
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function applyPlanAccess(Builder $query): Builder
    {
        if ($this->isBranchRestricted()) {
            $branchId = $this->context->branch()?->getKey();
            $query->where(function (Builder $query) use ($branchId): void {
                $query->whereNull('branch_id')->orWhere('branch_id', $branchId);
            });
        }

        return $query;
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function applyEnrolmentAccess(Builder $query): Builder
    {
        if ($this->isBranchRestricted()) {
            $query->where('branch_id', $this->context->branch()?->getKey());
        }

        return $query;
    }

    public function assertPlanScope(?Branch $branch, ?Group $group): void
    {
        $organisationId = $this->context->organisation()->getKey();

        if (
            ($branch !== null && $branch->organisation_id !== $organisationId)
            || ($group !== null && $group->organisation_id !== $organisationId)
            || ($group?->branch_id !== null && $branch?->getKey() !== $group->branch_id)
            || ($this->isBranchRestricted() && $branch?->getKey() !== $this->context->branch()?->getKey())
        ) {
            throw new InvalidContributionOperation;
        }
    }
}
