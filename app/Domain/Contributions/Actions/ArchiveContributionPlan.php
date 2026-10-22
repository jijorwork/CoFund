<?php

namespace App\Domain\Contributions\Actions;

use App\Domain\Contributions\Enums\ContributionPlanStatus;
use App\Domain\Contributions\Exceptions\InvalidContributionOperation;
use App\Domain\Contributions\Models\ContributionPlan;
use App\Domain\Contributions\Services\ContributionScope;

class ArchiveContributionPlan
{
    public function __construct(private ContributionScope $scope) {}

    public function handle(ContributionPlan $plan): ContributionPlan
    {
        $this->scope->assertAccessiblePlan($plan);

        if ($plan->status !== ContributionPlanStatus::Closed) {
            throw new InvalidContributionOperation;
        }

        $plan->update(['status' => ContributionPlanStatus::Archived]);

        return $plan->refresh()->load(['branch', 'group']);
    }
}
