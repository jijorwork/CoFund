<?php

namespace App\Domain\Contributions\Queries;

use App\Domain\Contributions\Enums\ObligationStatus;
use App\Domain\Contributions\Models\ContributionObligation;
use App\Domain\Contributions\Models\MemberPlanEnrolment;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ListContributionSchedule
{
    /** @param array<string, mixed> $filters
     * @return LengthAwarePaginator<int, ContributionObligation>
     */
    public function handle(MemberPlanEnrolment $enrolment, array $filters): LengthAwarePaginator
    {
        $query = ContributionObligation::query()
            ->whereBelongsTo($enrolment, 'enrolment')
            ->with('enrolment.contributionPlan.organisation')
            ->when($filters['due_from'] ?? null, fn (Builder $query, string $value) => $query->whereDate('due_date', '>=', $value))
            ->when($filters['due_to'] ?? null, fn (Builder $query, string $value) => $query->whereDate('due_date', '<=', $value));

        $this->applyStatusFilter($query, $enrolment, $filters['status'] ?? null);

        return $query
            ->orderBy('sequence_number')
            ->paginate(25)
            ->withQueryString();
    }

    /** @param Builder<ContributionObligation> $query */
    private function applyStatusFilter(
        Builder $query,
        MemberPlanEnrolment $enrolment,
        ?string $status,
    ): void {
        if ($status === null) {
            return;
        }

        $today = CarbonImmutable::now(
            $enrolment->contributionPlan->organisation->timezone,
        )->toDateString();

        match ($status) {
            ObligationStatus::Upcoming->value => $query
                ->whereDate('due_date', '>', $today)
                ->where('status', '!=', ObligationStatus::Cancelled),
            ObligationStatus::Due->value => $query
                ->whereDate('due_date', '<=', $today)
                ->whereDate('grace_due_date', '>=', $today)
                ->where('status', '!=', ObligationStatus::Cancelled),
            ObligationStatus::Overdue->value => $query
                ->whereDate('grace_due_date', '<', $today)
                ->where('outstanding_amount_minor', '>', 0)
                ->where('status', '!=', ObligationStatus::Cancelled),
            default => $query->where('status', $status),
        };
    }
}
