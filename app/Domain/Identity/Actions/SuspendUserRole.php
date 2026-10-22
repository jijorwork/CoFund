<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Enums\RoleAssignmentStatus;
use App\Domain\Identity\Exceptions\InvalidRoleAssignment;
use App\Domain\Identity\Models\OrganisationUserRole;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Organisations\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

class SuspendUserRole
{
    public function __construct(
        private RoleAuthorization $authorization,
        private TenantContext $tenantContext,
    ) {}

    public function handle(OrganisationUserRole $assignment): OrganisationUserRole
    {
        $this->authorization->authorize(FoundationPermission::RolesRevoke->value);
        $this->ensureAssignmentIsInContext($assignment);

        if ($this->tenantContext->roleAssignment()->is($assignment)) {
            throw new InvalidRoleAssignment('The active role context cannot suspend itself.');
        }

        if ($assignment->status !== RoleAssignmentStatus::Active) {
            throw new InvalidRoleAssignment('Only an active role assignment can be suspended.');
        }

        return DB::transaction(function () use ($assignment): OrganisationUserRole {
            $lockedAssignment = OrganisationUserRole::query()
                ->lockForUpdate()
                ->whereKey($assignment->getKey())
                ->firstOrFail();

            if ($lockedAssignment->status !== RoleAssignmentStatus::Active) {
                throw new InvalidRoleAssignment('Only an active role assignment can be suspended.');
            }

            $lockedAssignment->update([
                'status' => RoleAssignmentStatus::Suspended,
                'active_scope_key' => null,
            ]);

            return $lockedAssignment->refresh();
        });
    }

    private function ensureAssignmentIsInContext(OrganisationUserRole $assignment): void
    {
        $branch = $this->tenantContext->branch();

        if (
            $assignment->organisation_id !== $this->tenantContext->organisation()->getKey()
            || ($branch !== null && $assignment->branch_id !== null && $assignment->branch_id !== $branch->getKey())
        ) {
            throw new InvalidRoleAssignment;
        }
    }
}
