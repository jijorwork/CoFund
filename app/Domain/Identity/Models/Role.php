<?php

namespace App\Domain\Identity\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    /**
     * @return HasMany<OrganisationUserRole, $this>
     */
    public function organisationUserRoleAssignments(): HasMany
    {
        return $this->hasMany(OrganisationUserRole::class);
    }
}
