<?php

namespace App\Domain\Organisations\Actions;

use App\Domain\Identity\Enums\SystemRole;
use App\Domain\Organisations\Enums\OrganisationStatus;
use App\Domain\Organisations\Exceptions\InvalidOrganisationStatusTransition;
use App\Domain\Organisations\Models\Organisation;
use App\Domain\Organisations\Models\OrganisationStatusHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ChangeOrganisationStatus
{
    public function handle(
        User $actor,
        Organisation $organisation,
        OrganisationStatus $toStatus,
        ?string $reason = null,
    ): OrganisationStatusHistory {
        if (! $actor->hasRole(SystemRole::PlatformAdmin->value, 'web')) {
            throw new InvalidOrganisationStatusTransition('Only a platform administrator may change organisation status.');
        }

        $fromStatus = $organisation->status;

        if (! $this->isAllowed($fromStatus, $toStatus)) {
            throw new InvalidOrganisationStatusTransition;
        }

        if ($toStatus === OrganisationStatus::Suspended && blank($reason)) {
            throw new InvalidOrganisationStatusTransition('A suspension reason is required.');
        }

        return DB::transaction(function () use ($actor, $organisation, $fromStatus, $toStatus, $reason): OrganisationStatusHistory {
            $lockedOrganisation = Organisation::query()
                ->whereKey($organisation->getKey())
                ->lockForUpdate()
                ->sole();

            if ($lockedOrganisation->status !== $fromStatus) {
                throw new InvalidOrganisationStatusTransition('The organisation status changed before this action completed.');
            }

            $lockedOrganisation->update(['status' => $toStatus]);

            return OrganisationStatusHistory::query()->create([
                'organisation_id' => $lockedOrganisation->getKey(),
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'reason' => filled($reason) ? $reason : null,
                'changed_by_user_id' => $actor->getKey(),
                'metadata' => [
                    'source' => 'platform_filament',
                ],
            ]);
        });
    }

    private function isAllowed(OrganisationStatus $fromStatus, OrganisationStatus $toStatus): bool
    {
        return match ($fromStatus) {
            OrganisationStatus::Pending => $toStatus === OrganisationStatus::Active,
            OrganisationStatus::Active => in_array(
                $toStatus,
                [OrganisationStatus::Suspended, OrganisationStatus::Inactive],
                true,
            ),
            OrganisationStatus::Suspended, OrganisationStatus::Inactive => $toStatus === OrganisationStatus::Active,
        };
    }
}
