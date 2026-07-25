<?php

namespace App\Domain\Identity\Services;

use App\Domain\Identity\Models\OrganisationUserRole;
use App\Domain\Organisations\Enums\BranchStatus;
use App\Domain\Organisations\Enums\OrganisationAccessStatus;
use App\Domain\Organisations\Enums\OrganisationStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class ListAvailableUserRoleContexts
{
    /**
     * @return Collection<int, OrganisationUserRole>
     */
    public function forUser(User $user): Collection
    {
        return OrganisationUserRole::query()
            ->currentlyValid()
            ->whereBelongsTo($user)
            ->whereHas('organisation', fn ($query) => $query
                ->where('status', OrganisationStatus::Active))
            ->whereHas('organisation.organisationAccesses', fn ($query) => $query
                ->whereBelongsTo($user)
                ->where('status', OrganisationAccessStatus::Active))
            ->where(fn ($query) => $query
                ->whereNull('branch_id')
                ->orWhereHas('branch', fn ($query) => $query
                    ->where('status', BranchStatus::Active)))
            ->with(['organisation', 'branch', 'role'])
            ->latest()
            ->get();
    }
}
