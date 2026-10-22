<?php

namespace App\Filament\Resources\Organisations\Pages;

use App\Filament\Resources\Organisations\OrganisationResource;
use Filament\Resources\Pages\ListRecords;

class ListOrganisations extends ListRecords
{
    protected static string $resource = OrganisationResource::class;
}
