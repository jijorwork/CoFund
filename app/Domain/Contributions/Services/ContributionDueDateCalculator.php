<?php

namespace App\Domain\Contributions\Services;

use App\Domain\Contributions\Enums\ContributionFrequency;
use App\Domain\Contributions\Models\ContributionPlan;
use Carbon\CarbonImmutable;

class ContributionDueDateCalculator
{
    public function firstDueDate(ContributionPlan $plan, CarbonImmutable $start): ?CarbonImmutable
    {
        return match ($plan->frequency) {
            ContributionFrequency::Daily => $start,
            ContributionFrequency::Weekly => $start->addDays(
                (($plan->collection_day_of_week ?? $start->dayOfWeekIso) - $start->dayOfWeekIso + 7) % 7,
            ),
            ContributionFrequency::Monthly => $this->monthlyOnOrAfter(
                $start,
                $plan->collection_day_of_month ?? min($start->day, 28),
            ),
            ContributionFrequency::OneTime => $plan->start_date === null
                ? $start
                : CarbonImmutable::parse($plan->start_date->toDateString()),
            ContributionFrequency::Flexible => null,
        };
    }

    public function nextDueDate(ContributionPlan $plan, CarbonImmutable $current): ?CarbonImmutable
    {
        return match ($plan->frequency) {
            ContributionFrequency::Daily => $current->addDay(),
            ContributionFrequency::Weekly => $current->addWeek(),
            ContributionFrequency::Monthly => $current->addMonthNoOverflow(),
            ContributionFrequency::OneTime, ContributionFrequency::Flexible => null,
        };
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    public function period(ContributionPlan $plan, CarbonImmutable $dueDate): array
    {
        return match ($plan->frequency) {
            ContributionFrequency::Weekly => [$dueDate->subDays(6), $dueDate],
            ContributionFrequency::Monthly => [$dueDate->startOfMonth(), $dueDate->endOfMonth()],
            default => [$dueDate, $dueDate],
        };
    }

    public function graceDueDate(ContributionPlan $plan, CarbonImmutable $dueDate): CarbonImmutable
    {
        return $dueDate->addDays($plan->grace_period_days);
    }

    private function monthlyOnOrAfter(CarbonImmutable $start, int $day): CarbonImmutable
    {
        $candidate = $start->setDay($day);

        return $candidate->isBefore($start) ? $candidate->addMonthNoOverflow() : $candidate;
    }
}
