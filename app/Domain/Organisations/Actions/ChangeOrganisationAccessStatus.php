<?php

namespace App\Domain\Organisations\Actions;

use App\Domain\Identity\Auditing\ActingContext;
use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Models\OrganisationUserRole;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Organisations\Enums\OrganisationAccessStatus;
use App\Domain\Organisations\Exceptions\InvalidOrganisationAccess;
use App\Domain\Organisations\Models\OrganisationAccessStatusHistory;
use App\Domain\Organisations\Models\OrganisationUser;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ChangeOrganisationAccessStatus
{
    public function __construct(
        private RoleAuthorization $authorization,
        private TenantContext $tenantContext,
    ) {}

    public function handle(
        OrganisationUser $access,
        OrganisationAccessStatus $to,
        User $actor,
        ?string $reason = null,
    ): OrganisationUser {
        $this->authorization->authorize(FoundationPermission::OrganisationUsersManage->value);
        $this->assertContextAllows($access, $actor);

        return DB::transaction(function () use ($access, $to, $actor, $reason): OrganisationUser {
            $locked = OrganisationUser::query()
                ->lockForUpdate()
                ->whereKey($access->getKey())
                ->firstOrFail();

            $this->assertContextAllows($locked, $actor);
            $from = $locked->status;

            if (! in_array($to, $from->allowedTransitions(), true)) {
                throw new InvalidOrganisationAccess('The organisation access status transition is not allowed.');
            }

            if (
                in_array($to, [OrganisationAccessStatus::Suspended, OrganisationAccessStatus::Revoked], true)
                && ($reason === null || trim($reason) === '')
            ) {
                throw new InvalidOrganisationAccess('A reason is required for this access status change.');
            }

            $acting = ActingContext::from($actor, $this->tenantContext);
            $locked->update([
                'status' => $to,
                'joined_at' => $to === OrganisationAccessStatus::Active
                    ? ($locked->joined_at ?? now())
                    : $locked->joined_at,
            ]);

            OrganisationAccessStatusHistory::query()->create([
                'organisation_id' => $acting->organisationId,
                'organisation_user_id' => $locked->getKey(),
                'from_status' => $from,
                'to_status' => $to,
                'reason' => $reason,
                'effective_at' => now(),
                'changed_by_user_id' => $acting->userId,
                'acting_role_id' => $acting->roleId,
            ]);

            return $locked->refresh();
        });
    }

    private function assertContextAllows(OrganisationUser $access, User $actor): void
    {
        $roleAssignment = $this->tenantContext->roleAssignment();

        if (
            $access->organisation_id !== $this->tenantContext->organisation()->getKey()
            || $roleAssignment->user_id !== $actor->getKey()
        ) {
            throw new InvalidOrganisationAccess;
        }

        if ($access->user_id === $actor->getKey()) {
            throw new InvalidOrganisationAccess('The active administrator cannot change their own organisation access.');
        }

        $branch = $this->tenantContext->branch();

        if ($branch === null) {
            return;
        }

        $isRelevant = OrganisationUserRole::query()
            ->whereBelongsTo($this->tenantContext->organisation())
            ->where('user_id', $access->user_id)
            ->currentlyValid()
            ->where(fn (Builder $query): Builder => $query
                ->whereBelongsTo($branch)
                ->orWhereNull('branch_id'))
            ->exists();

        if (! $isRelevant) {
            throw new InvalidOrganisationAccess('The organisation access is outside the active branch context.');
        }
    }
}
