<?php

namespace App\Domain\Contributions\Services;

use App\Domain\Contributions\Enums\ContributionAmountType;
use App\Domain\Contributions\Enums\ContributionFrequency;
use App\Domain\Contributions\Exceptions\InvalidContributionOperation;
use App\Domain\Contributions\Models\ContributionPlan;

class ValidateContributionPlanConfiguration
{
    public function assertValid(ContributionPlan $plan): void
    {
        if ($plan->end_date !== null && $plan->start_date !== null && $plan->end_date->isBefore($plan->start_date)) {
            throw new InvalidContributionOperation('Plan end date cannot precede its start date.');
        }

        match ($plan->amount_type) {
            ContributionAmountType::Fixed => $this->assertFixed($plan),
            ContributionAmountType::MemberSelected => $this->assertMemberSelected($plan),
            ContributionAmountType::Flexible => $this->assertFlexible($plan),
        };

        if (
            $plan->frequency === ContributionFrequency::Monthly
            && ($plan->collection_day_of_month !== null
                && ($plan->collection_day_of_month < 1 || $plan->collection_day_of_month > 28))
        ) {
            throw new InvalidContributionOperation('Monthly collection day must be between 1 and 28.');
        }

        if (
            $plan->frequency === ContributionFrequency::Weekly
            && ($plan->collection_day_of_week !== null
                && ($plan->collection_day_of_week < 1 || $plan->collection_day_of_week > 7))
        ) {
            throw new InvalidContributionOperation('Weekly collection day uses ISO 1 through 7.');
        }

        if ($plan->frequency === ContributionFrequency::OneTime && $plan->start_date === null) {
            throw new InvalidContributionOperation('A one-time plan requires a start date.');
        }

        if (
            $plan->amount_type === ContributionAmountType::Flexible
            && $plan->frequency !== ContributionFrequency::Flexible
        ) {
            throw new InvalidContributionOperation('Flexible amounts require flexible frequency.');
        }
    }

    private function assertFixed(ContributionPlan $plan): void
    {
        if ($plan->fixed_amount_minor === null || $plan->fixed_amount_minor <= 0) {
            throw new InvalidContributionOperation('A fixed plan requires a positive amount.');
        }
    }

    private function assertMemberSelected(ContributionPlan $plan): void
    {
        if (
            $plan->fixed_amount_minor !== null
            || $plan->minimum_amount_minor === null
            || $plan->minimum_amount_minor <= 0
            || ($plan->maximum_amount_minor !== null
                && $plan->maximum_amount_minor < $plan->minimum_amount_minor)
        ) {
            throw new InvalidContributionOperation('Member-selected amount range is invalid.');
        }
    }

    private function assertFlexible(ContributionPlan $plan): void
    {
        if (
            $plan->fixed_amount_minor !== null
            || ($plan->minimum_amount_minor !== null && $plan->minimum_amount_minor < 0)
            || ($plan->maximum_amount_minor !== null
                && $plan->minimum_amount_minor !== null
                && $plan->maximum_amount_minor < $plan->minimum_amount_minor)
        ) {
            throw new InvalidContributionOperation('Flexible amount bounds are invalid.');
        }
    }
}
