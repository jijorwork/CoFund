<?php

namespace App\Filament\Resources\RoleAssignments\Pages;

use App\Filament\Resources\RoleAssignments\OrganisationUserRoleResource;
use Filament\Resources\Pages\ListRecords;

class ListOrganisationUserRoles extends ListRecords
{
    protected static string $resource = OrganisationUserRoleResource::class;
}
