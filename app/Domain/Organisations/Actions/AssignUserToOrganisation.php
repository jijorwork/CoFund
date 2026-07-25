<?php

namespace App\Domain\Organisations\Actions;

use App\Domain\Organisations\Enums\OrganisationAccessStatus;
use App\Domain\Organisations\Models\Organisation;
use App\Domain\Organisations\Models\OrganisationUser;
use App\Models\User;
use Carbon\CarbonInterface;

class AssignUserToOrganisation
{
    public function handle(
        Organisation $organisation,
        User $user,
        OrganisationAccessStatus $status = OrganisationAccessStatus::Invited,
        ?CarbonInterface $joinedAt = null,
    ): OrganisationUser {
        return OrganisationUser::query()->create([
            'organisation_id' => $organisation->getKey(),
            'user_id' => $user->getKey(),
            'status' => $status,
            'joined_at' => $joinedAt,
        ]);
    }
}
