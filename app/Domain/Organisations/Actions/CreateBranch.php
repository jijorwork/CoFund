<?php

namespace App\Domain\Organisations\Actions;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Organisations\Enums\BranchStatus;
use App\Domain\Organisations\Exceptions\BranchOutsideTenant;
use App\Domain\Organisations\Exceptions\InvalidOrganisationAccess;
use App\Domain\Organisations\Models\Branch;
use App\Domain\Organisations\Models\Organisation;
use App\Domain\Organisations\Tenancy\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

class CreateBranch
{
    public function __construct(
        private RoleAuthorization $authorization,
        private TenantContext $tenantContext,
    ) {}

    /**
     * @param  array{
     *     name: string,
     *     code: string,
     *     phone?: string|null,
     *     email?: string|null,
     *     address?: string|null,
     *     status?: BranchStatus
     * }  $attributes
     */
    public function handle(Organisation $organisation, array $attributes): Branch
    {
        $this->authorization->authorize(FoundationPermission::BranchesCreate->value);

        if (! $organisation->is($this->tenantContext->organisation())) {
            throw new BranchOutsideTenant;
        }

        if ($this->tenantContext->branch() !== null) {
            throw new InvalidOrganisationAccess(
                'Branches can only be created from an organisation-wide context.',
            );
        }

        try {
            return $organisation->branches()->create([
                ...$attributes,
                'public_id' => (string) Str::uuid7(),
                'code' => Str::upper($attributes['code']),
                'status' => $attributes['status'] ?? BranchStatus::Active,
            ]);
        } catch (QueryException $exception) {
            throw new InvalidOrganisationAccess(
                'A branch with that code already exists in the active organisation.',
                previous: $exception,
            );
        }
    }
}
