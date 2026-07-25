<?php

namespace App\Domain\Identity\Services;

use App\Domain\Identity\Models\OrganisationUserRole;
use App\Domain\Organisations\Tenancy\TenantContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class RoleAssignmentDirectory
{
    public function __construct(private TenantContext $tenantContext) {}

    /**
     * @param  array{user_uuid?: string, role_name?: string, branch_uuid?: string, status?: string}  $filters
     * @return LengthAwarePaginator<int, OrganisationUserRole>
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        return OrganisationUserRole::query()
            ->whereBelongsTo($this->tenantContext->organisation())
            ->with(['organisation', 'branch', 'user', 'role'])
            ->when($filters['user_uuid'] ?? null, fn ($query, string $uuid) => $query
                ->whereHas('user', fn ($query) => $query->where('public_id', $uuid)))
            ->when($filters['role_name'] ?? null, fn ($query, string $name) => $query
                ->whereHas('role', fn ($query) => $query->where('name', $name)))
            ->when($filters['branch_uuid'] ?? null, fn ($query, string $uuid) => $query
                ->whereHas('branch', fn ($query) => $query->where('public_id', $uuid)))
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query
                ->where('status', $status))
            ->latest()
            ->paginate(25)
            ->withQueryString();
    }

    public function findByPublicId(string $publicId): ?OrganisationUserRole
    {
        return OrganisationUserRole::query()
            ->whereBelongsTo($this->tenantContext->organisation())
            ->with(['organisation', 'branch', 'user', 'role'])
            ->where('public_id', $publicId)
            ->first();
    }
}
