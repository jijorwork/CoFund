<?php

namespace App\Filament\Resources\ContributionPlans\Pages;

use App\Filament\Resources\ContributionPlans\ContributionPlanResource;
use Filament\Resources\Pages\ViewRecord;

class ViewContributionPlan extends ViewRecord
{
    protected static string $resource = ContributionPlanResource::class;

    protected function getHeaderActions(): array
    {
        return ContributionPlanResource::planActions();
    }
}
