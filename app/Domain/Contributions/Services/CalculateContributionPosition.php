<?php

namespace App\Domain\Contributions\Services;

use App\Domain\Contributions\Data\ContributionPosition;
use App\Domain\Contributions\Enums\ContributionPositionStatus;
use App\Domain\Contributions\Enums\EnrolmentStatus;
use App\Domain\Contributions\Enums\ObligationStatus;
use App\Domain\Contributions\Models\MemberPlanEnrolment;
use Carbon\CarbonImmutable;

class CalculateContributionPosition
{
    public function calculate(MemberPlanEnrolment $enrolment): ContributionPosition
    {
        $today = CarbonImmutable::now($enrolment->contributionPlan->organisation->timezone)->toDateString();
        $obligations = $enrolment->relationLoaded('obligations')
            ? $enrolment->obligations
            : $enrolment->obligations()->get();
        $count = $obligations->count();
        $due = $obligations->filter(fn ($obligation): bool => $obligation->due_date->toDateString() <= $today
            && $obligation->schedule_status !== ObligationStatus::Cancelled);
        $expected = (int) $due->sum('expected_amount_minor');
        $satisfied = (int) $due->sum('satisfied_amount_minor');
        $outstanding = max(0, $expected - $satisfied);
        $overdueCount = $obligations->filter(fn ($obligation): bool => $obligation->grace_due_date !== null
            && $obligation->grace_due_date->toDateString() < $today
            && $obligation->outstanding_amount_minor > 0
            && $obligation->schedule_status !== ObligationStatus::Cancelled)->count();
        $upcomingCount = $obligations->filter(fn ($obligation): bool => $obligation->due_date->toDateString() > $today
            && $obligation->schedule_status !== ObligationStatus::Cancelled)->count();
        $next = $obligations
            ->filter(fn ($obligation): bool => $obligation->due_date->toDateString() >= $today
                && $obligation->schedule_status !== ObligationStatus::Cancelled)
            ->sortBy('due_date')
            ->first();

        return new ContributionPosition(
            expectedToDateMinor: $expected,
            satisfiedToDateMinor: $satisfied,
            outstandingToDateMinor: $outstanding,
            overdueObligationCount: $overdueCount,
            upcomingObligationCount: $upcomingCount,
            nextDueDate: $next?->due_date->toDateString(),
            nextExpectedAmountMinor: $next?->expected_amount_minor,
            status: $this->status($enrolment, $count, $overdueCount, $satisfied, $expected),
        );
    }

    private function status(
        MemberPlanEnrolment $enrolment,
        int $obligationCount,
        int $overdueCount,
        int $satisfied,
        int $expected,
    ): ContributionPositionStatus {
        if ($enrolment->status === EnrolmentStatus::Suspended) {
            return ContributionPositionStatus::Suspended;
        }

        if (in_array($enrolment->status, [EnrolmentStatus::Ended, EnrolmentStatus::Cancelled, EnrolmentStatus::Completed], true)) {
            return ContributionPositionStatus::Ended;
        }

        if ($obligationCount === 0) {
            return ContributionPositionStatus::NoSchedule;
        }

        if ($overdueCount > 0) {
            return ContributionPositionStatus::InArrears;
        }

        if ($satisfied > $expected) {
            return ContributionPositionStatus::Prepaid;
        }

        return ContributionPositionStatus::Current;
    }
}
