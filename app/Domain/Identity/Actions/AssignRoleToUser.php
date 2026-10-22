<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Enums\RoleAssignmentStatus;
use App\Domain\Identity\Enums\SystemRole;
use App\Domain\Identity\Exceptions\InvalidRoleAssignment;
use App\Domain\Identity\Models\OrganisationUserRole;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Organisations\Enums\OrganisationStatus;
use App\Domain\Organisations\Models\Branch;
use App\Domain\Organisations\Models\Organisation;
use App\Domain\Organisations\Services\OrganisationAccessService;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class AssignRoleToUser
{
    public function __construct(
        private RoleAuthorization $authorization,
        private OrganisationAccessService $organisationAccess,
        private TenantContext $tenantContext,
    ) {}

    public function handle(
        User $assigningUser,
        Organisation $organisation,
        User $targetUser,
        Role $role,
        ?Branch $branch = null,
        ?CarbonInterface $startsAt = null,
        ?CarbonInterface $endsAt = null,
    ): OrganisationUserRole {
        $this->authorization->authorize(FoundationPermission::RolesAssign->value);

        if (
            ! $this->authorization->hasActiveRole()
            || ! $this->tenantContext->roleAssignment()->user->is($assigningUser)
        ) {
            throw new InvalidRoleAssignment;
        }

        if ($organisation->status !== OrganisationStatus::Active) {
            throw new InvalidRoleAssignment('Roles cannot be assigned in an inactive organisation.');
        }

        if (! $this->organisationAccess->hasActiveAccess($targetUser, $organisation)) {
            throw new InvalidRoleAssignment('The target user does not have active organisation access.');
        }

        $systemRole = SystemRole::tryFrom($role->name);

        if ($systemRole === null || ! $systemRole->isOrganisationAssignable()) {
            throw new InvalidRoleAssignment('The selected role is not organisation assignable.');
        }

        if ($branch !== null && $branch->organisation_id !== $organisation->getKey()) {
            throw new InvalidRoleAssignment('The selected branch does not belong to the organisation.');
        }

        $activeBranch = $this->tenantContext->branch();

        if ($activeBranch !== null && ($branch === null || ! $branch->is($activeBranch))) {
            throw new InvalidRoleAssignment('The role assignment must remain within the active branch context.');
        }

        if ($endsAt !== null && $startsAt !== null && $endsAt->lessThanOrEqualTo($startsAt)) {
            throw new InvalidRoleAssignment('The assignment end must be after its start.');
        }

        return DB::transaction(function () use (
            $assigningUser,
            $organisation,
            $targetUser,
            $role,
            $branch,
            $startsAt,
            $endsAt,
        ): OrganisationUserRole {
            $activeScopeKey = OrganisationUserRole::activeScopeKey(
                $organisation,
                $targetUser,
                $role,
                $branch,
            );

            if (OrganisationUserRole::query()->where('active_scope_key', $activeScopeKey)->lockForUpdate()->exists()) {
                throw new InvalidRoleAssignment('An active duplicate role assignment already exists.');
            }

            return OrganisationUserRole::query()->create([
                'organisation_id' => $organisation->getKey(),
                'branch_id' => $branch?->getKey(),
                'user_id' => $targetUser->getKey(),
                'role_id' => $role->getKey(),
                'status' => RoleAssignmentStatus::Active,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'assigned_by_user_id' => $assigningUser->getKey(),
                'assigned_at' => now(),
                'active_scope_key' => $activeScopeKey,
            ]);
        });
    }
}
