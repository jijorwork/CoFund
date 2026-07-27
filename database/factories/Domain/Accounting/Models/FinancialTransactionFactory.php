<?php

namespace Database\Factories\Domain\Accounting\Models;

use App\Domain\Accounting\Enums\AccountCategory;
use App\Domain\Accounting\Enums\EntryType;
use App\Domain\Accounting\Enums\FinancialTransactionStatus;
use App\Domain\Accounting\Enums\FinancialTransactionType;
use App\Domain\Accounting\Models\FinancialTransaction;
use App\Domain\Accounting\Models\LedgerAccount;
use App\Domain\Accounting\Models\TransactionEntry;
use App\Domain\Organisations\Models\Organisation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<FinancialTransaction> */
class FinancialTransactionFactory extends Factory
{
    protected $model = FinancialTransaction::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'organisation_id' => Organisation::factory(),
            'branch_id' => null,
            'transaction_number' => fake()->unique()->bothify('ORG-TXN-2026-######'),
            'transaction_type' => FinancialTransactionType::Adjustment,
            'status' => FinancialTransactionStatus::Posted,
            'currency_code' => 'GHS',
            'transaction_date' => now()->toDateString(),
            'posted_at' => now(),
            'description' => 'Balanced factory transaction',
            'idempotency_key' => fake()->uuid(),
            'request_fingerprint' => hash('sha256', fake()->uuid()),
            'created_by_user_id' => User::factory(),
            'posted_by_user_id' => User::factory(),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (FinancialTransaction $transaction): void {
            if (
                $transaction->status !== FinancialTransactionStatus::Posted
                || $transaction->entries()->exists()
            ) {
                return;
            }

            $debit = LedgerAccount::factory()->asset()->for($transaction->organisation)->create([
                'currency_code' => $transaction->currency_code,
            ]);
            $credit = LedgerAccount::factory()->category(AccountCategory::Liability)->for($transaction->organisation)->create([
                'currency_code' => $transaction->currency_code,
            ]);

            foreach ([[$debit, EntryType::Debit], [$credit, EntryType::Credit]] as $index => [$account, $type]) {
                TransactionEntry::query()->create([
                    'organisation_id' => $transaction->organisation_id,
                    'financial_transaction_id' => $transaction->getKey(),
                    'ledger_account_id' => $account->getKey(),
                    'entry_type' => $type,
                    'amount_minor' => 10000,
                    'currency_code' => $transaction->currency_code,
                    'sequence_number' => $index + 1,
                ]);
            }
        });
    }

    public function draft(): static
    {
        return $this->state(fn (): array => [
            'status' => FinancialTransactionStatus::Draft,
            'posted_at' => null,
            'posted_by_user_id' => null,
        ]);
    }

    public function posted(): static
    {
        return $this->state(fn (): array => ['status' => FinancialTransactionStatus::Posted]);
    }

    public function reversed(): static
    {
        return $this->state(fn (): array => ['status' => FinancialTransactionStatus::Reversed]);
    }

    public function failed(): static
    {
        return $this->state(fn (): array => ['status' => FinancialTransactionStatus::Failed, 'posted_at' => null]);
    }

    public function openingBalance(): static
    {
        return $this->state(fn (): array => ['transaction_type' => FinancialTransactionType::OpeningBalance]);
    }

    public function ordinaryBalancedTransaction(): static
    {
        return $this->posted();
    }
}
