<?php

namespace Database\Factories\Domain\Withdrawals\Models;

use App\Domain\Organisations\Models\Organisation;
use App\Domain\Withdrawals\Models\WithdrawalProduct;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WithdrawalProduct> */
class WithdrawalProductFactory extends Factory
{
    protected $model = WithdrawalProduct::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['organisation_id' => Organisation::factory(), 'code' => fake()->unique()->bothify('WDR-####'), 'name' => fake()->words(2, true), 'product_type' => 'ordinary_savings', 'currency_code' => 'GHS', 'eligible_savings_wallet_type' => 'member_savings', 'fee_type' => 'none', 'fee_treatment' => 'deducted_from_payout', 'status' => 'active', 'created_by_user_id' => User::factory(), 'activated_at' => now()];
    }
}
