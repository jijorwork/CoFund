<?php

namespace App\Domain\Contributions\Services;

use App\Domain\Contributions\Enums\ContributionFrequency;
use App\Domain\Contributions\Enums\ContributionPlanStatus;
use App\Domain\Contributions\Enums\EnrolmentStatus;
use App\Domain\Contributions\Enums\ObligationStatus;
use App\Domain\Contributions\Exceptions\InvalidContributionOperation;
use App\Domain\Contributions\Models\ContributionObligation;
use App\Domain\Contributions\Models\MemberPlanEnrolment;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class GenerateContributionSchedule
{
    public function __construct(
        private ContributionDueDateCalculator $dates,
        private ContributionPlanAmount $amounts,
    ) {}

    public function generate(MemberPlanEnrolment $enrolment, bool $extend = false): MemberPlanEnrolment
    {
        return DB::transaction(function () use ($enrolment, $extend): MemberPlanEnrolment {
            $locked = MemberPlanEnrolment::query()
                ->with('contributionPlan.organisation')
                ->lockForUpdate()
                ->whereKey($enrolment->getKey())
                ->firstOrFail();

            if (
                $locked->status !== EnrolmentStatus::Active
                || $locked->contributionPlan->status !== ContributionPlanStatus::Active
            ) {
                throw new InvalidContributionOperation('Only active enrolments have schedules.');
            }

            $plan = $locked->contributionPlan;

            if ($plan->frequency === ContributionFrequency::Flexible) {
                $locked->update(['next_due_date' => null]);

                return $locked->refresh()->load(['contributionPlan', 'member', 'branch', 'group']);
            }

            $start = CarbonImmutable::parse($locked->start_date->toDateString());
            $today = CarbonImmutable::now($plan->organisation->timezone)->startOfDay();
            $basis = $extend && $today->isAfter($start) ? $today : $start;
            $horizonEnd = $this->horizonEnd($plan->frequency, $basis);
            $dueDate = $this->dates->firstDueDate($plan, $start);
            $sequence = 0;
            $expectedAmount = $this->amounts->expectedAmount($plan, $locked->selected_amount_minor);

            while ($dueDate !== null && $dueDate->lessThanOrEqualTo($horizonEnd)) {
                if ($this->pastEndDate($locked, $dueDate)) {
                    break;
                }

                $sequence++;
                [$periodStart, $periodEnd] = $this->dates->period($plan, $dueDate);
                $graceDueDate = $this->dates->graceDueDate($plan, $dueDate);
                $status = $this->statusFor($dueDate, $graceDueDate, $today);

                ContributionObligation::query()->firstOrCreate([
                    'member_plan_enrolment_id' => $locked->getKey(),
                    'due_date' => $dueDate,
                ], [
                    'organisation_id' => $locked->organisation_id,
                    'contribution_plan_id' => $plan->getKey(),
                    'member_id' => $locked->member_id,
                    'sequence_number' => $sequence,
                    'period_start' => $periodStart->toDateString(),
                    'period_end' => $periodEnd->toDateString(),
                    'expected_amount_minor' => $expectedAmount,
                    'currency_code' => $locked->currency_code,
                    'schedule_status' => $status,
                    'grace_due_date' => $graceDueDate->toDateString(),
                    'satisfied_amount_minor' => 0,
                    'outstanding_amount_minor' => $expectedAmount ?? 0,
                    'generated_at' => now(),
                ]);

                $dueDate = $this->dates->nextDueDate($plan, $dueDate);
            }

            $nextDueDate = ContributionObligation::query()
                ->whereBelongsTo($locked, 'enrolment')
                ->whereDate('due_date', '>=', $today->toDateString())
                ->where('schedule_status', '!=', ObligationStatus::Cancelled)
                ->orderBy('due_date')
                ->value('due_date');
            $locked->update(['next_due_date' => $nextDueDate]);

            return $locked->refresh()->load(['contributionPlan', 'member', 'branch', 'group']);
        });
    }

    private function horizonEnd(ContributionFrequency $frequency, CarbonImmutable $basis): CarbonImmutable
    {
        return match ($frequency) {
            ContributionFrequency::Daily => $basis->addDays((int) config('contributions.schedule_horizon.daily_days', 60) - 1),
            ContributionFrequency::Weekly => $basis->addWeeks((int) config('contributions.schedule_horizon.weekly_weeks', 12) - 1),
            ContributionFrequency::Monthly => $basis->addMonthsNoOverflow((int) config('contributions.schedule_horizon.monthly_months', 12) - 1),
            ContributionFrequency::OneTime => $basis,
            ContributionFrequency::Flexible => $basis,
        };
    }

    private function pastEndDate(MemberPlanEnrolment $enrolment, CarbonImmutable $dueDate): bool
    {
        $planEnd = $enrolment->contributionPlan->end_date;

        return ($enrolment->end_date !== null && $dueDate->isAfter($enrolment->end_date))
            || ($planEnd !== null && $dueDate->isAfter($planEnd));
    }

    private function statusFor(
        CarbonImmutable $dueDate,
        CarbonImmutable $graceDueDate,
        CarbonImmutable $today,
    ): ObligationStatus {
        if ($graceDueDate->isBefore($today)) {
            return ObligationStatus::Overdue;
        }

        return $dueDate->lessThanOrEqualTo($today)
            ? ObligationStatus::Due
            : ObligationStatus::Upcoming;
    }
}
