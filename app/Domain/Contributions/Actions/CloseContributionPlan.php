<?php

namespace App\Domain\Contributions\Actions;

use App\Domain\Contributions\Enums\ContributionPlanStatus;
use App\Domain\Contributions\Exceptions\InvalidContributionOperation;
use App\Domain\Contributions\Models\ContributionPlan;

class CloseContributionPlan
{
    public function handle(ContributionPlan $plan): ContributionPlan
    {
        if (! in_array($plan->status, [ContributionPlanStatus::Active, ContributionPlanStatus::Suspended], true)) {
            throw new InvalidContributionOperation;
        }

        $plan->update(['status' => ContributionPlanStatus::Closed, 'closed_at' => now()]);

        return $plan->refresh()->load(['branch', 'group']);
    }
}
