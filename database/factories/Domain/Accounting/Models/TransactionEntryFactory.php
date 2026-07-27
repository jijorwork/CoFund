<?php

namespace Database\Factories\Domain\Accounting\Models;

use App\Domain\Accounting\Enums\EntryType;
use App\Domain\Accounting\Models\FinancialTransaction;
use App\Domain\Accounting\Models\LedgerAccount;
use App\Domain\Accounting\Models\TransactionEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TransactionEntry> */
class TransactionEntryFactory extends Factory
{
    protected $model = TransactionEntry::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'financial_transaction_id' => FinancialTransaction::factory()->draft(),
            'organisation_id' => fn (array $attributes): int => FinancialTransaction::query()
                ->findOrFail((int) $attributes['financial_transaction_id'])
                ->organisation_id,
            'ledger_account_id' => function (array $attributes): LedgerAccount {
                $transaction = FinancialTransaction::query()
                    ->findOrFail((int) $attributes['financial_transaction_id']);

                return LedgerAccount::factory()->for($transaction->organisation)->create([
                    'currency_code' => $transaction->currency_code,
                ]);
            },
            'entry_type' => EntryType::Debit,
            'amount_minor' => 10000,
            'currency_code' => 'GHS',
            'sequence_number' => fake()->unique()->numberBetween(1, 65000),
        ];
    }
}
