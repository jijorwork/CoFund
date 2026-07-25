<?php

namespace App\Domain\Identity\Services;

use App\Domain\Identity\Enums\SystemRole;
use App\Domain\Identity\Exceptions\PermissionDenied;
use App\Domain\Organisations\Tenancy\TenantContext;

class RoleAuthorization
{
    public function __construct(private TenantContext $tenantContext) {}

    public function can(string $permission): bool
    {
        return $this->hasActiveRole()
            && $this->tenantContext->role()->checkPermissionTo($permission, 'web');
    }

    public function authorize(string $permission): void
    {
        if (! $this->can($permission)) {
            throw new PermissionDenied;
        }
    }

    public function hasActiveRole(): bool
    {
        return $this->tenantContext->hasRole()
            && $this->tenantContext->roleAssignment()->isCurrentlyValid();
    }

    public function isPlatformAdministrator(): bool
    {
        return $this->hasActiveRole()
            && $this->tenantContext->role()->name === SystemRole::PlatformAdmin->value;
    }
}
