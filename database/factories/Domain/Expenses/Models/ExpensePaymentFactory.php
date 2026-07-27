<?php

namespace Database\Factories\Domain\Expenses\Models;

use App\Domain\Accounting\Models\FinancialTransaction;
use App\Domain\Expenses\Models\ExpensePayment;
use App\Domain\Expenses\Models\ExpenseRequest;
use App\Domain\Wallets\Models\Wallet;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExpensePayment>
 */
class ExpensePaymentFactory extends Factory
{
    protected $model = ExpensePayment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'expense_request_id' => ExpenseRequest::factory()->approved(),
            'organisation_id' => fn (array $attributes) => ExpenseRequest::query()->whereKey($attributes['expense_request_id'])->firstOrFail()->organisation_id,
            'payment_number' => fake()->unique()->bothify('ORG-EPM-2026-######'),
            'payment_type' => 'direct_expense',
            'source_wallet_id' => Wallet::factory(),
            'financial_transaction_id' => FinancialTransaction::factory(),
            'amount_minor' => 10000, 'currency_code' => 'GHS', 'payment_method' => 'cash',
            'payment_date' => today(), 'payee_name' => fake()->name(), 'status' => 'posted',
            'idempotency_key' => fake()->uuid(), 'request_fingerprint' => hash('sha256', fake()->uuid()),
            'paid_by_user_id' => User::factory(),
        ];
    }
}
