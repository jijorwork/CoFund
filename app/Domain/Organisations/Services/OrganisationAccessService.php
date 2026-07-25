<?php

namespace App\Domain\Organisations\Services;

use App\Domain\Organisations\Models\Organisation;
use App\Domain\Organisations\Models\OrganisationUser;
use App\Models\User;

class OrganisationAccessService
{
    public function hasActiveAccess(User $user, Organisation $organisation): bool
    {
        return OrganisationUser::query()
            ->active()
            ->whereBelongsTo($user)
            ->whereBelongsTo($organisation)
            ->exists();
    }
}
