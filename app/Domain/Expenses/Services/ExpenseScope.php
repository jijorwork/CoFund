<?php

namespace App\Domain\Expenses\Services;

use App\Domain\Expenses\Exceptions\InvalidExpenseOperation;
use App\Domain\Organisations\Tenancy\TenantContext;

class ExpenseScope
{
    public function __construct(private TenantContext $context) {}

    public function assertBranch(?int $branchId): void
    {
        if ($this->context->roleAssignment()->branch_id !== null && $branchId !== $this->context->branch()?->getKey()) {
            throw new InvalidExpenseOperation('The record is outside the active branch.');
        }
    }
}
