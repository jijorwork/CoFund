<?php

namespace App\Domain\Contributions\Actions;

use App\Domain\Contributions\Enums\ContributionPlanStatus;
use App\Domain\Contributions\Exceptions\InvalidContributionOperation;
use App\Domain\Contributions\Models\ContributionPlan;
use App\Domain\Contributions\Services\ValidateContributionPlanConfiguration;

class ActivateContributionPlan
{
    public function __construct(private ValidateContributionPlanConfiguration $validator) {}

    public function handle(ContributionPlan $plan): ContributionPlan
    {
        if (! in_array($plan->status, [ContributionPlanStatus::Draft, ContributionPlanStatus::Suspended], true)) {
            throw new InvalidContributionOperation;
        }

        $this->validator->assertValid($plan);
        $plan->update([
            'status' => ContributionPlanStatus::Active,
            'activated_at' => $plan->activated_at ?? now(),
            'suspended_at' => null,
        ]);

        return $plan->refresh()->load(['branch', 'group']);
    }
}
