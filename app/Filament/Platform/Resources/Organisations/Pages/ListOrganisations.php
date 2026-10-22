<?php

namespace App\Filament\Platform\Resources\Organisations\Pages;

use App\Filament\Platform\Resources\Organisations\OrganisationResource;
use Filament\Resources\Pages\ListRecords;

class ListOrganisations extends ListRecords
{
    protected static string $resource = OrganisationResource::class;
}
