<?php

namespace App\Domain\Organisations\Actions;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Organisations\Enums\OrganisationAccessStatus;
use App\Domain\Organisations\Exceptions\InvalidOrganisationAccess;
use App\Domain\Organisations\Models\OrganisationUser;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Models\User;
use Illuminate\Database\QueryException;

class GrantOrganisationAccess
{
    public function __construct(
        private RoleAuthorization $authorization,
        private TenantContext $tenantContext,
        private AssignUserToOrganisation $assignUser,
    ) {}

    public function handle(
        User $user,
        OrganisationAccessStatus $status = OrganisationAccessStatus::Active,
    ): OrganisationUser {
        $this->authorization->authorize(FoundationPermission::OrganisationUsersManage->value);

        $organisation = $this->tenantContext->organisation();

        if (OrganisationUser::query()
            ->whereBelongsTo($organisation)
            ->whereBelongsTo($user)
            ->exists()) {
            throw new InvalidOrganisationAccess('The user already has organisation access.');
        }

        try {
            return $this->assignUser->handle(
                organisation: $organisation,
                user: $user,
                status: $status,
                joinedAt: $status === OrganisationAccessStatus::Active ? now() : null,
                grantedBy: $this->tenantContext->roleAssignment()->user,
            );
        } catch (QueryException $exception) {
            throw new InvalidOrganisationAccess(
                'The user already has organisation access.',
                previous: $exception,
            );
        }
    }
}
