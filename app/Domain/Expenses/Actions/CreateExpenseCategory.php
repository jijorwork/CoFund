<?php

namespace App\Domain\Expenses\Actions;

use App\Domain\Accounting\Enums\AccountCategory;
use App\Domain\Accounting\Enums\LedgerAccountStatus;
use App\Domain\Accounting\Models\LedgerAccount;
use App\Domain\Expenses\Exceptions\InvalidExpenseOperation;
use App\Domain\Expenses\Models\ExpenseCategory;
use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Models\User;

class CreateExpenseCategory
{
    public function __construct(private TenantContext $context, private RoleAuthorization $authorization) {}

    /** @param array<string,mixed> $data */
    public function handle(User $actor, LedgerAccount $account, array $data, ?ExpenseCategory $parent = null): ExpenseCategory
    {
        $this->authorization->authorize(FoundationPermission::ExpenseCategoriesCreate->value);
        $org = $this->context->organisation();
        if ($account->organisation_id !== $org->getKey() || $account->category !== AccountCategory::Expense || $account->status !== LedgerAccountStatus::Active || ! $account->allows_direct_posting || ($parent && $parent->organisation_id !== $org->getKey())) {
            throw new InvalidExpenseOperation('Invalid expense category ancestry or account.');
        }

        return ExpenseCategory::query()->create([...$data, 'organisation_id' => $org->getKey(), 'parent_id' => $parent?->getKey(), 'default_ledger_account_id' => $account->getKey(), 'created_by_user_id' => $actor->getKey()]);
    }
}
