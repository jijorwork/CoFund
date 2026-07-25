<?php

namespace App\Domain\Contributions\Queries;

use App\Domain\Contributions\Models\MemberPlanEnrolment;
use App\Domain\Contributions\Services\ContributionScope;
use App\Domain\Organisations\Tenancy\TenantContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ListContributionEnrolments
{
    public function __construct(private TenantContext $context, private ContributionScope $scope) {}

    /** @param array<string, mixed> $filters
     * @return LengthAwarePaginator<int, MemberPlanEnrolment>
     */
    public function handle(array $filters, string $sort): LengthAwarePaginator
    {
        $query = MemberPlanEnrolment::query()
            ->whereBelongsTo($this->context->organisation())
            ->with(['contributionPlan.organisation', 'member', 'branch', 'group', 'obligations'])
            ->when($filters['member_uuid'] ?? null, fn (Builder $query, string $value) => $query->whereHas('member', fn (Builder $query) => $query->where('public_id', $value)))
            ->when($filters['plan_uuid'] ?? null, fn (Builder $query, string $value) => $query->whereHas('contributionPlan', fn (Builder $query) => $query->where('public_id', $value)))
            ->when($filters['branch_uuid'] ?? null, fn (Builder $query, string $value) => $query->whereIn('branch_id', BranchIdByUuid::query($value)))
            ->when($filters['group_uuid'] ?? null, fn (Builder $query, string $value) => $query->whereIn('group_id', GroupIdByUuid::query($value)))
            ->when($filters['status'] ?? null, fn (Builder $query, string $value) => $query->where('status', $value))
            ->when($filters['approval_status'] ?? null, fn (Builder $query, string $value) => $query->where('approval_status', $value))
            ->when($filters['start_date_from'] ?? null, fn (Builder $query, string $value) => $query->whereDate('start_date', '>=', $value))
            ->when($filters['start_date_to'] ?? null, fn (Builder $query, string $value) => $query->whereDate('start_date', '<=', $value));
        $this->scope->applyEnrolmentAccess($query);
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';

        return $query->orderBy(ltrim($sort, '-'), $direction)->paginate(25)->withQueryString();
    }
}
