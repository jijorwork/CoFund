<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Enums\RoleAssignmentStatus;
use App\Domain\Identity\Exceptions\InvalidRoleAssignment;
use App\Domain\Identity\Models\OrganisationUserRole;
use Illuminate\Support\Facades\DB;

class ReinstateUserRole
{
    public function handle(OrganisationUserRole $assignment): OrganisationUserRole
    {
        return DB::transaction(function () use ($assignment): OrganisationUserRole {
            $activeScopeKey = OrganisationUserRole::activeScopeKey(
                $assignment->organisation,
                $assignment->user,
                $assignment->role,
                $assignment->branch,
            );

            if (OrganisationUserRole::query()->where('active_scope_key', $activeScopeKey)->lockForUpdate()->exists()) {
                throw new InvalidRoleAssignment('An active duplicate role assignment already exists.');
            }

            $assignment->update([
                'status' => RoleAssignmentStatus::Active,
                'active_scope_key' => $activeScopeKey,
            ]);

            return $assignment->refresh();
        });
    }
}
