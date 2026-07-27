<?php

namespace Database\Factories\Domain\Welfare\Models;

use App\Domain\Accounting\Models\FinancialTransaction;
use App\Domain\Wallets\Models\Wallet;
use App\Domain\Welfare\Models\WelfareBenefitPayment;
use App\Domain\Welfare\Models\WelfareClaim;
use App\Domain\Welfare\Models\WelfareClaimLiability;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WelfareBenefitPayment> */
class WelfareBenefitPaymentFactory extends Factory
{
    protected $model = WelfareBenefitPayment::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['organisation_id' => fn (array $attributes) => WelfareClaim::query()->whereKey($attributes['welfare_claim_id'])->value('organisation_id'), 'welfare_claim_id' => WelfareClaim::factory(), 'welfare_claim_liability_id' => WelfareClaimLiability::factory(), 'payment_number' => fake()->unique()->bothify('WPA-####'), 'source_wallet_id' => Wallet::factory(), 'recipient_type' => 'member', 'recipient_name' => fake()->name(), 'amount_minor' => 10000, 'currency_code' => 'GHS', 'payment_method' => 'cash', 'payment_date' => today(), 'status' => 'posted', 'financial_transaction_id' => FinancialTransaction::factory(), 'paid_by_user_id' => User::factory(), 'idempotency_key' => fake()->uuid(), 'request_fingerprint' => hash('sha256', fake()->uuid())];
    }
}
