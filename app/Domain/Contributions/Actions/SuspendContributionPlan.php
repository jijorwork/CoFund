<?php

namespace App\Domain\Contributions\Actions;

use App\Domain\Contributions\Enums\ContributionPlanStatus;
use App\Domain\Contributions\Exceptions\InvalidContributionOperation;
use App\Domain\Contributions\Models\ContributionPlan;

class SuspendContributionPlan
{
    public function handle(ContributionPlan $plan): ContributionPlan
    {
        if ($plan->status !== ContributionPlanStatus::Active) {
            throw new InvalidContributionOperation;
        }

        $plan->update(['status' => ContributionPlanStatus::Suspended, 'suspended_at' => now()]);

        return $plan->refresh()->load(['branch', 'group']);
    }
}
