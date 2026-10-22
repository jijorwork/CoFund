<?php

namespace App\Domain\Organisations\Actions;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Organisations\Exceptions\InvalidOrganisationAccess;
use App\Domain\Organisations\Models\Organisation;
use App\Domain\Organisations\Tenancy\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UpdateOrganisationProfile
{
    public function __construct(
        private RoleAuthorization $authorization,
        private TenantContext $tenantContext,
    ) {}

    /**
     * @param  array{
     *     name: string,
     *     code: string,
     *     phone: string|null,
     *     email: string|null,
     *     address: string|null
     * }  $attributes
     */
    public function handle(Organisation $organisation, array $attributes): Organisation
    {
        $this->authorization->authorize(FoundationPermission::OrganisationsUpdate->value);
        $this->assertContextAllows($organisation);

        try {
            return DB::transaction(function () use ($organisation, $attributes): Organisation {
                $lockedOrganisation = Organisation::query()
                    ->lockForUpdate()
                    ->whereKey($organisation->getKey())
                    ->firstOrFail();

                $this->assertContextAllows($lockedOrganisation);
                $lockedOrganisation->update([
                    ...$attributes,
                    'code' => Str::upper($attributes['code']),
                ]);

                return $lockedOrganisation->refresh();
            });
        } catch (QueryException $exception) {
            throw new InvalidOrganisationAccess(
                'An organisation with that code already exists.',
                previous: $exception,
            );
        }
    }

    private function assertContextAllows(Organisation $organisation): void
    {
        if (! $organisation->is($this->tenantContext->organisation())) {
            throw new InvalidOrganisationAccess(
                'The organisation is outside the verified active context.',
            );
        }

        if ($this->tenantContext->branch() !== null) {
            throw new InvalidOrganisationAccess(
                'Organisation profiles can only be updated from an organisation-wide context.',
            );
        }
    }
}
