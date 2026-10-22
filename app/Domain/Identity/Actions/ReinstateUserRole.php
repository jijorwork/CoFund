<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Enums\RoleAssignmentStatus;
use App\Domain\Identity\Enums\SystemRole;
use App\Domain\Identity\Exceptions\InvalidRoleAssignment;
use App\Domain\Identity\Models\OrganisationUserRole;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Organisations\Enums\OrganisationStatus;
use App\Domain\Organisations\Services\OrganisationAccessService;
use App\Domain\Organisations\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

class ReinstateUserRole
{
    public function __construct(
        private RoleAuthorization $authorization,
        private TenantContext $tenantContext,
        private OrganisationAccessService $organisationAccess,
    ) {}

    public function handle(OrganisationUserRole $assignment): OrganisationUserRole
    {
        $this->authorization->authorize(FoundationPermission::RolesAssign->value);
        $this->ensureAssignmentCanBeReinstated($assignment);

        return DB::transaction(function () use ($assignment): OrganisationUserRole {
            $lockedAssignment = OrganisationUserRole::query()
                ->with(['organisation', 'branch', 'user', 'role'])
                ->lockForUpdate()
                ->whereKey($assignment->getKey())
                ->firstOrFail();

            $this->ensureAssignmentCanBeReinstated($lockedAssignment);

            $activeScopeKey = OrganisationUserRole::activeScopeKey(
                $lockedAssignment->organisation,
                $lockedAssignment->user,
                $lockedAssignment->role,
                $lockedAssignment->branch,
            );

            if (OrganisationUserRole::query()->where('active_scope_key', $activeScopeKey)->lockForUpdate()->exists()) {
                throw new InvalidRoleAssignment('An active duplicate role assignment already exists.');
            }

            $lockedAssignment->update([
                'status' => RoleAssignmentStatus::Active,
                'active_scope_key' => $activeScopeKey,
            ]);

            return $lockedAssignment->refresh();
        });
    }

    private function ensureAssignmentCanBeReinstated(OrganisationUserRole $assignment): void
    {
        $branch = $this->tenantContext->branch();
        $systemRole = SystemRole::tryFrom($assignment->role->name);

        if (
            $assignment->organisation_id !== $this->tenantContext->organisation()->getKey()
            || ($branch !== null && $assignment->branch_id !== null && $assignment->branch_id !== $branch->getKey())
        ) {
            throw new InvalidRoleAssignment;
        }

        if ($assignment->status !== RoleAssignmentStatus::Suspended) {
            throw new InvalidRoleAssignment('Only a suspended role assignment can be reinstated.');
        }

        if ($assignment->organisation->status !== OrganisationStatus::Active) {
            throw new InvalidRoleAssignment('Roles cannot be reinstated in an inactive organisation.');
        }

        if (! $this->organisationAccess->hasActiveAccess($assignment->user, $assignment->organisation)) {
            throw new InvalidRoleAssignment('The target user does not have active organisation access.');
        }

        if ($systemRole === null || ! $systemRole->isOrganisationAssignable()) {
            throw new InvalidRoleAssignment('The selected role is not organisation assignable.');
        }

        if ($assignment->ends_at !== null && $assignment->ends_at->lessThanOrEqualTo(now())) {
            throw new InvalidRoleAssignment('An expired role assignment cannot be reinstated.');
        }
    }
}
