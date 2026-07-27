<?php

namespace App\Domain\Accounting\Actions;

use App\Domain\Accounting\Enums\AccountCategory;
use App\Domain\Accounting\Exceptions\InvalidFinancialOperation;
use App\Domain\Accounting\Models\LedgerAccount;
use App\Domain\Accounting\Services\AccountingScope;
use App\Domain\Organisations\Models\Branch;

class UpdateLedgerAccount
{
    private const SYSTEM_IMMUTABLE = [
        'code', 'category', 'normal_balance', 'purpose', 'currency_code',
        'allows_direct_posting', 'branch_id', 'parent_id',
    ];

    public function __construct(private AccountingScope $scope) {}

    /** @param array<string, mixed> $attributes */
    public function handle(LedgerAccount $account, array $attributes, ?Branch $branch, ?LedgerAccount $parent): LedgerAccount
    {
        $this->scope->assertBranch($branch);

        if ($account->is_system_account && array_intersect(array_keys($attributes), self::SYSTEM_IMMUTABLE) !== []) {
            throw new InvalidFinancialOperation('System account classification is immutable.');
        }

        if (
            $parent !== null
            && ($parent->organisation_id !== $account->organisation_id
                || $parent->currency_code !== $account->currency_code
                || $this->wouldCreateCycle($account, $parent))
        ) {
            throw new InvalidFinancialOperation;
        }

        if (array_key_exists('category', $attributes)) {
            $category = AccountCategory::from((string) $attributes['category']);
            $attributes['normal_balance'] = $category->normalBalance();
        }

        $account->update([
            ...$attributes,
            'branch_id' => $branch?->getKey(),
            'parent_id' => $parent?->getKey(),
        ]);

        return $account->refresh()->load(['branch', 'parent']);
    }

    private function wouldCreateCycle(LedgerAccount $account, LedgerAccount $parent): bool
    {
        $current = $parent;

        while ($current !== null) {
            if ($current->is($account)) {
                return true;
            }

            $current = $current->parent;
        }

        return false;
    }
}
