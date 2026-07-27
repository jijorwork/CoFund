<?php

namespace Database\Factories\Domain\Withdrawals\Models;

use App\Domain\Accounting\Models\FinancialTransaction;
use App\Domain\Wallets\Models\Wallet;
use App\Domain\Withdrawals\Models\WithdrawalPayout;
use App\Domain\Withdrawals\Models\WithdrawalRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WithdrawalPayout> */
class WithdrawalPayoutFactory extends Factory
{
    protected $model = WithdrawalPayout::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['organisation_id' => fn (array $attributes) => WithdrawalRequest::query()->whereKey($attributes['withdrawal_request_id'])->value('organisation_id'), 'withdrawal_request_id' => WithdrawalRequest::factory(), 'payout_number' => fake()->unique()->bothify('WDP-####'), 'member_savings_wallet_id' => Wallet::factory(), 'destination_wallet_id' => Wallet::factory(), 'recipient_type' => 'member', 'recipient_name' => fake()->name(), 'gross_withdrawal_minor' => 10000, 'fee_minor' => 0, 'net_payout_minor' => 10000, 'currency_code' => 'GHS', 'payment_method' => 'cash', 'payout_date' => today(), 'status' => 'posted', 'financial_transaction_id' => FinancialTransaction::factory(), 'paid_by_user_id' => User::factory(), 'idempotency_key' => fake()->uuid(), 'request_fingerprint' => hash('sha256', fake()->uuid())];
    }
}
