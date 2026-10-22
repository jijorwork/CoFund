<?php

namespace App\Filament\Resources\OrganisationAccess\Pages;

use App\Filament\Resources\OrganisationAccess\OrganisationUserResource;
use Filament\Resources\Pages\ListRecords;

class ListOrganisationUsers extends ListRecords
{
    protected static string $resource = OrganisationUserResource::class;
}
