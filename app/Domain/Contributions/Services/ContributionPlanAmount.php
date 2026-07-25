<?php

namespace App\Domain\Contributions\Services;

use App\Domain\Contributions\Enums\ContributionAmountType;
use App\Domain\Contributions\Exceptions\InvalidContributionOperation;
use App\Domain\Contributions\Models\ContributionPlan;

class ContributionPlanAmount
{
    public function expectedAmount(ContributionPlan $plan, ?int $selectedAmountMinor): ?int
    {
        return match ($plan->amount_type) {
            ContributionAmountType::Fixed => $this->fixed($plan),
            ContributionAmountType::MemberSelected => $this->selected($plan, $selectedAmountMinor),
            ContributionAmountType::Flexible => null,
        };
    }

    private function fixed(ContributionPlan $plan): int
    {
        if ($plan->fixed_amount_minor === null || $plan->fixed_amount_minor <= 0) {
            throw new InvalidContributionOperation;
        }

        return $plan->fixed_amount_minor;
    }

    private function selected(ContributionPlan $plan, ?int $amount): int
    {
        if (
            $amount === null
            || $amount <= 0
            || ($plan->minimum_amount_minor !== null && $amount < $plan->minimum_amount_minor)
            || ($plan->maximum_amount_minor !== null && $amount > $plan->maximum_amount_minor)
        ) {
            throw new InvalidContributionOperation('Selected amount is outside the plan range.');
        }

        return $amount;
    }
}
