<?php

namespace App\Domain\Organisations\Services;

use App\Domain\Organisations\Models\Branch;
use App\Domain\Organisations\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Collection;

class BranchDirectory
{
    public function __construct(private TenantContext $tenantContext) {}

    /**
     * @return Collection<int, Branch>
     */
    public function all(): Collection
    {
        return Branch::query()
            ->forOrganisation($this->tenantContext->organisation())
            ->latest()
            ->get();
    }

    public function findByPublicId(string $publicId): ?Branch
    {
        return Branch::query()
            ->forOrganisation($this->tenantContext->organisation())
            ->where('public_id', $publicId)
            ->first();
    }
}
