<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Enums\RoleAssignmentStatus;
use App\Domain\Identity\Models\OrganisationUserRole;
use Illuminate\Support\Facades\DB;

class SuspendUserRole
{
    public function handle(OrganisationUserRole $assignment): OrganisationUserRole
    {
        return DB::transaction(function () use ($assignment): OrganisationUserRole {
            $assignment->update([
                'status' => RoleAssignmentStatus::Suspended,
                'active_scope_key' => null,
            ]);

            return $assignment->refresh();
        });
    }
}
