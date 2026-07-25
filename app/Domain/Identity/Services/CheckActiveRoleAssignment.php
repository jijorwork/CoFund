<?php

namespace App\Domain\Identity\Services;

use App\Domain\Identity\Models\OrganisationUserRole;
use App\Domain\Organisations\Enums\BranchStatus;
use App\Domain\Organisations\Enums\OrganisationStatus;
use App\Domain\Organisations\Models\Branch;
use App\Domain\Organisations\Models\Organisation;
use App\Domain\Organisations\Services\OrganisationAccessService;
use App\Models\User;

class CheckActiveRoleAssignment
{
    public function __construct(private OrganisationAccessService $organisationAccess) {}

    public function passes(
        OrganisationUserRole $assignment,
        User $user,
        Organisation $organisation,
        ?Branch $branch,
    ): bool {
        if (
            ! $assignment->user->is($user)
            || ! $assignment->organisation->is($organisation)
            || $organisation->status !== OrganisationStatus::Active
            || ! $this->organisationAccess->hasActiveAccess($user, $organisation)
            || ! $assignment->isCurrentlyValid()
        ) {
            return false;
        }

        if ($branch !== null && (
            $branch->organisation_id !== $organisation->getKey()
            || $branch->status !== BranchStatus::Active
        )) {
            return false;
        }

        return $assignment->branch_id === null
            || ($branch !== null && $assignment->branch_id === $branch->getKey());
    }
}
