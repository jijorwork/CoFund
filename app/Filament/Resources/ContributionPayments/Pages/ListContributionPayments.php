<?php

namespace App\Filament\Resources\ContributionPayments\Pages;

use App\Filament\Resources\ContributionPayments\ContributionPaymentResource;
use Filament\Resources\Pages\ListRecords;

class ListContributionPayments extends ListRecords
{
    protected static string $resource = ContributionPaymentResource::class;
}
