<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Exceptions\InvalidRoleContext;
use App\Domain\Identity\Models\OrganisationUserRole;
use App\Domain\Identity\Services\CheckActiveRoleAssignment;
use App\Domain\Organisations\Models\Branch;
use App\Domain\Organisations\Models\Organisation;
use App\Models\User;
use Illuminate\Contracts\Session\Session;

class SwitchActiveRoleContext
{
    public function __construct(private CheckActiveRoleAssignment $checkAssignment) {}

    public function handle(
        User $user,
        Organisation $organisation,
        ?Branch $branch,
        OrganisationUserRole $assignment,
        Session $session,
    ): void {
        if (! $this->checkAssignment->passes($assignment, $user, $organisation, $branch)) {
            throw new InvalidRoleContext;
        }

        $session->put('active_context', [
            'organisation_public_id' => $organisation->public_id,
            'branch_public_id' => $branch?->public_id,
            'role_assignment_public_id' => $assignment->public_id,
        ]);
    }
}
