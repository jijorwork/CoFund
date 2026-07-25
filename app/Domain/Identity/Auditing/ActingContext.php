<?php

namespace App\Domain\Identity\Auditing;

use App\Domain\Organisations\Tenancy\TenantContext;
use App\Models\User;

final readonly class ActingContext
{
    public function __construct(
        public int $userId,
        public int $organisationId,
        public ?int $branchId,
        public int $roleId,
        public int $roleAssignmentId,
    ) {}

    public static function from(User $user, TenantContext $tenantContext): self
    {
        return new self(
            userId: (int) $user->getKey(),
            organisationId: (int) $tenantContext->organisation()->getKey(),
            branchId: $tenantContext->branch() === null
                ? null
                : (int) $tenantContext->branch()->getKey(),
            roleId: (int) $tenantContext->role()->getKey(),
            roleAssignmentId: (int) $tenantContext->roleAssignment()->getKey(),
        );
    }
}
