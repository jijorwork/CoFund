<?php

namespace App\Domain\Contributions\Actions;

use App\Domain\Contributions\Models\ContributionPlan;

class ReinstateContributionPlan
{
    public function __construct(private ActivateContributionPlan $activate) {}

    public function handle(ContributionPlan $plan): ContributionPlan
    {
        return $this->activate->handle($plan);
    }
}
