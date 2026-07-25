<?php

namespace App\Domain\Contributions\Queries;

use App\Domain\Contributions\Models\ContributionPlan;
use App\Domain\Contributions\Services\ContributionScope;
use App\Domain\Organisations\Tenancy\TenantContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ListContributionPlans
{
    public function __construct(private TenantContext $context, private ContributionScope $scope) {}

    /** @param array<string, mixed> $filters
     * @return LengthAwarePaginator<int, ContributionPlan>
     */
    public function handle(array $filters, string $sort): LengthAwarePaginator
    {
        $query = ContributionPlan::query()
            ->whereBelongsTo($this->context->organisation())
            ->with(['branch', 'group'])
            ->when($filters['status'] ?? null, fn (Builder $query, string $value) => $query->where('status', $value))
            ->when($filters['plan_type'] ?? null, fn (Builder $query, string $value) => $query->where('plan_type', $value))
            ->when($filters['frequency'] ?? null, fn (Builder $query, string $value) => $query->where('frequency', $value))
            ->when($filters['currency_code'] ?? null, fn (Builder $query, string $value) => $query->where('currency_code', $value))
            ->when($filters['branch_uuid'] ?? null, fn (Builder $query, string $value) => $query->whereIn('branch_id', BranchIdByUuid::query($value)))
            ->when($filters['group_uuid'] ?? null, fn (Builder $query, string $value) => $query->whereIn('group_id', GroupIdByUuid::query($value)));
        $this->scope->applyPlanAccess($query);
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';

        return $query->orderBy(ltrim($sort, '-'), $direction)->paginate(25)->withQueryString();
    }
}
