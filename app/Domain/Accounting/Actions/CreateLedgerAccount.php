<?php

namespace App\Domain\Accounting\Actions;

use App\Domain\Accounting\Enums\AccountCategory;
use App\Domain\Accounting\Enums\LedgerAccountStatus;
use App\Domain\Accounting\Exceptions\InvalidFinancialOperation;
use App\Domain\Accounting\Models\LedgerAccount;
use App\Domain\Accounting\Services\AccountingScope;
use App\Domain\Organisations\Models\Branch;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Models\User;

class CreateLedgerAccount
{
    public function __construct(private TenantContext $context, private AccountingScope $scope) {}

    /** @param array<string, mixed> $attributes */
    public function handle(User $actor, array $attributes, ?Branch $branch, ?LedgerAccount $parent): LedgerAccount
    {
        $this->scope->assertBranch($branch);
        $organisation = $this->context->organisation();
        $category = $attributes['category'] instanceof AccountCategory
            ? $attributes['category']
            : AccountCategory::from($attributes['category']);

        if (
            strtoupper($attributes['currency_code'] ?? $organisation->currency_code) !== strtoupper($organisation->currency_code)
            || ($parent !== null && (
                $parent->organisation_id !== $organisation->getKey()
                || strtoupper($parent->currency_code) !== strtoupper($organisation->currency_code)
            ))
        ) {
            throw new InvalidFinancialOperation;
        }

        return LedgerAccount::query()->create([
            ...$attributes,
            'organisation_id' => $organisation->getKey(),
            'branch_id' => $branch?->getKey(),
            'parent_id' => $parent?->getKey(),
            'category' => $category,
            'normal_balance' => $category->normalBalance(),
            'currency_code' => strtoupper($organisation->currency_code),
            'is_system_account' => false,
            'status' => LedgerAccountStatus::Draft,
            'created_by_user_id' => $actor->getKey(),
        ])->load(['branch', 'parent']);
    }
}
