<?php

namespace App\Domain\Reconciliation\Services;

use App\Domain\Identity\Enums\SystemRole;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Domain\Reconciliation\Exceptions\InvalidReconciliationOperation;
use App\Models\User;

class ReconciliationScope
{
    public function __construct(private TenantContext $context) {}

    public function assertBranch(?int $branchId): void
    {
        if (
            $this->context->roleAssignment()->branch_id !== null
            && $branchId !== $this->context->branch()?->getKey()
        ) {
            throw new InvalidReconciliationOperation;
        }
    }

    public function assertCollectorActor(User $actor, int $collectorUserId): void
    {
        if (
            $this->context->role()->name === SystemRole::Collector->value
            && $actor->getKey() !== $collectorUserId
        ) {
            throw new InvalidReconciliationOperation('Collectors may operate only their own cash records.');
        }
    }
}
