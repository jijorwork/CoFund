<?php

namespace App\Domain\Accounting\Services;

use App\Domain\Accounting\Exceptions\InvalidFinancialOperation;
use App\Domain\Organisations\Models\Branch;
use App\Domain\Organisations\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AccountingScope
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
    public function apply(Builder $query, bool $includeOrganisationWide = false): Builder
    {
        if ($this->isBranchRestricted()) {
            $branchId = $this->context->branch()?->getKey();

            $includeOrganisationWide
                ? $query->where(fn (Builder $query) => $query
                    ->whereNull('branch_id')
                    ->orWhere('branch_id', $branchId))
                : $query->where('branch_id', $branchId);
        }

        return $query;
    }

    public function assertBranch(?Branch $branch, bool $allowOrganisationWide = false): void
    {
        if (
            ($branch !== null && $branch->organisation_id !== $this->context->organisation()->getKey())
            || ($this->isBranchRestricted()
                && (! $allowOrganisationWide || $branch !== null)
                && $branch?->getKey() !== $this->context->branch()?->getKey())
        ) {
            throw new InvalidFinancialOperation;
        }
    }
}
