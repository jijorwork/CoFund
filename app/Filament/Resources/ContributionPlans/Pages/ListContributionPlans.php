<?php

namespace App\Filament\Resources\ContributionPlans\Pages;

use App\Filament\Resources\ContributionPlans\ContributionPlanResource;
use Filament\Resources\Pages\ListRecords;

class ListContributionPlans extends ListRecords
{
    protected static string $resource = ContributionPlanResource::class;
}
