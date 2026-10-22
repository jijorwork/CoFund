<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Enums\RoleAssignmentStatus;
use App\Domain\Identity\Exceptions\InvalidRoleAssignment;
use App\Domain\Identity\Models\OrganisationUserRole;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RevokeUserRole
{
    public function __construct(
        private RoleAuthorization $authorization,
        private TenantContext $tenantContext,
    ) {}

    public function handle(
        OrganisationUserRole $assignment,
        User $revokingUser,
        ?string $reason = null,
    ): OrganisationUserRole {
        $this->authorization->authorize(FoundationPermission::RolesRevoke->value);

        if ($assignment->organisation_id !== $this->tenantContext->organisation()->getKey()) {
            throw new InvalidRoleAssignment;
        }

        if ($this->tenantContext->roleAssignment()->is($assignment)) {
            throw new InvalidRoleAssignment('The active role context cannot revoke itself.');
        }

        $branch = $this->tenantContext->branch();

        if ($branch !== null && $assignment->branch_id !== null && $assignment->branch_id !== $branch->getKey()) {
            throw new InvalidRoleAssignment;
        }

        return DB::transaction(function () use ($assignment, $revokingUser, $reason): OrganisationUserRole {
            $lockedAssignment = OrganisationUserRole::query()
                ->lockForUpdate()
                ->whereKey($assignment->getKey())
                ->firstOrFail();

            if ($lockedAssignment->status === RoleAssignmentStatus::Revoked) {
                throw new InvalidRoleAssignment('The role assignment is already revoked.');
            }

            $lockedAssignment->update([
                'status' => RoleAssignmentStatus::Revoked,
                'revoked_by_user_id' => $revokingUser->getKey(),
                'revoked_at' => now(),
                'revocation_reason' => $reason,
                'active_scope_key' => null,
            ]);

            return $lockedAssignment->refresh();
        });
    }
}
