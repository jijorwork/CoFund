<?php

namespace Database\Factories\Domain\Reconciliation\Models;

use App\Domain\Organisations\Models\Organisation;
use App\Domain\Reconciliation\Enums\CashDepositMethod;
use App\Domain\Reconciliation\Enums\CashDepositStatus;
use App\Domain\Reconciliation\Models\CashDepositBatch;
use App\Domain\Wallets\Models\Wallet;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CashDepositBatch> */
class CashDepositBatchFactory extends Factory
{
    protected $model = CashDepositBatch::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $organisation = Organisation::factory()->create();
        $source = Wallet::factory()->officeCash()->create(['organisation_id' => $organisation->getKey()]);
        $destination = Wallet::factory()->bank()->create(['organisation_id' => $organisation->getKey()]);

        return [
            'organisation_id' => $organisation->getKey(), 'deposit_number' => fake()->unique()->bothify('ORG-DEP-2026-######'),
            'source_wallet_id' => $source->getKey(), 'destination_wallet_id' => $destination->getKey(),
            'deposit_method' => CashDepositMethod::BankCashDeposit, 'deposit_date' => today(), 'amount_minor' => 10000,
            'currency_code' => $organisation->currency_code, 'status' => CashDepositStatus::Draft,
            'deposited_by_user_id' => User::factory(), 'idempotency_key' => fake()->uuid(),
            'request_fingerprint' => hash('sha256', fake()->uuid()),
        ];
    }

    public function submitted(): static
    {
        return $this->state(fn (): array => ['status' => CashDepositStatus::Submitted]);
    }

    public function confirmed(): static
    {
        return $this->state(fn (): array => ['status' => CashDepositStatus::Confirmed, 'confirmed_at' => now()]);
    }

    public function rejected(): static
    {
        return $this->state(fn (): array => ['status' => CashDepositStatus::Rejected]);
    }
}
