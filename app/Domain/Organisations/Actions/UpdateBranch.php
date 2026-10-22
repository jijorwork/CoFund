<?php

namespace App\Domain\Organisations\Actions;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Organisations\Exceptions\BranchOutsideTenant;
use App\Domain\Organisations\Exceptions\InvalidOrganisationAccess;
use App\Domain\Organisations\Models\Branch;
use App\Domain\Organisations\Tenancy\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UpdateBranch
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
    public function handle(Branch $branch, array $attributes): Branch
    {
        $this->authorization->authorize(FoundationPermission::BranchesUpdate->value);
        $this->assertContextAllows($branch);

        try {
            return DB::transaction(function () use ($branch, $attributes): Branch {
                $lockedBranch = Branch::query()
                    ->lockForUpdate()
                    ->whereKey($branch->getKey())
                    ->firstOrFail();

                $this->assertContextAllows($lockedBranch);
                $lockedBranch->update([
                    ...$attributes,
                    'code' => Str::upper($attributes['code']),
                ]);

                return $lockedBranch->refresh();
            });
        } catch (QueryException $exception) {
            throw new InvalidOrganisationAccess(
                'A branch with that code already exists in the active organisation.',
                previous: $exception,
            );
        }
    }

    private function assertContextAllows(Branch $branch): void
    {
        if ($branch->organisation_id !== $this->tenantContext->organisation()->getKey()) {
            throw new BranchOutsideTenant;
        }

        $activeBranch = $this->tenantContext->branch();

        if ($activeBranch !== null && ! $branch->is($activeBranch)) {
            throw new BranchOutsideTenant;
        }
    }
}
