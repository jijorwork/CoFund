<?php

namespace Database\Factories\Domain\Reconciliation\Models;

use App\Domain\Accounting\Enums\AccountCategory;
use App\Domain\Accounting\Enums\AccountPurpose;
use App\Domain\Accounting\Models\LedgerAccount;
use App\Domain\Organisations\Models\Branch;
use App\Domain\Organisations\Models\Organisation;
use App\Domain\Reconciliation\Enums\CollectorSessionStatus;
use App\Domain\Reconciliation\Models\CollectorCollectionSession;
use App\Domain\Wallets\Enums\WalletOwnerType;
use App\Domain\Wallets\Enums\WalletStatus;
use App\Domain\Wallets\Enums\WalletType;
use App\Domain\Wallets\Models\Wallet;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CollectorCollectionSession> */
class CollectorCollectionSessionFactory extends Factory
{
    protected $model = CollectorCollectionSession::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $organisation = Organisation::factory()->create();
        $branch = Branch::factory()->for($organisation)->create();
        $collector = User::factory()->create();
        $account = LedgerAccount::factory()->for($organisation)->for($branch)->category(AccountCategory::Asset)->create(['purpose' => AccountPurpose::Cash]);
        $wallet = Wallet::factory()->create([
            'organisation_id' => $organisation->getKey(), 'branch_id' => $branch->getKey(),
            'walletable_type' => WalletOwnerType::CollectorUser->value, 'walletable_id' => $collector->getKey(),
            'ledger_account_id' => $account->getKey(), 'wallet_type' => WalletType::CollectorCash,
            'status' => WalletStatus::Active, 'active_owner_key' => 'collector:'.$collector->getKey().':collector_cash',
        ]);

        return [
            'organisation_id' => $organisation->getKey(), 'branch_id' => $branch->getKey(),
            'collector_user_id' => $collector->getKey(), 'collector_wallet_id' => $wallet->getKey(),
            'session_number' => fake()->unique()->bothify('ORG-COL-2026-######'), 'session_date' => today(),
            'opened_at' => now(), 'status' => CollectorSessionStatus::Open, 'currency_code' => $organisation->currency_code,
            'opened_by_user_id' => $collector->getKey(), 'active_wallet_key' => (string) $wallet->getKey(),
        ];
    }

    public function open(): static
    {
        return $this->state(fn (): array => ['status' => CollectorSessionStatus::Open]);
    }

    public function closed(): static
    {
        return $this->state(fn (): array => ['status' => CollectorSessionStatus::Closed, 'closed_at' => now(), 'active_wallet_key' => null]);
    }

    public function submitted(): static
    {
        return $this->closed()->state(fn (): array => ['status' => CollectorSessionStatus::Submitted, 'submitted_at' => now()]);
    }

    public function approved(): static
    {
        return $this->submitted()->state(fn (): array => ['status' => CollectorSessionStatus::Approved, 'approved_at' => now()]);
    }

    public function rejected(): static
    {
        return $this->submitted()->state(fn (): array => ['status' => CollectorSessionStatus::Rejected, 'rejected_at' => now(), 'rejection_reason' => 'Count requires correction']);
    }

    public function exact(): static
    {
        return $this->state(fn (): array => ['expected_cash_minor' => 10000, 'declared_cash_minor' => 10000, 'shortage_minor' => 0, 'overage_minor' => 0]);
    }

    public function shortage(): static
    {
        return $this->state(fn (): array => ['expected_cash_minor' => 10000, 'declared_cash_minor' => 9000, 'shortage_minor' => 1000, 'overage_minor' => 0]);
    }

    public function overage(): static
    {
        return $this->state(fn (): array => ['expected_cash_minor' => 10000, 'declared_cash_minor' => 11000, 'shortage_minor' => 0, 'overage_minor' => 1000]);
    }
}
