<?php

namespace Database\Factories\Domain\Expenses\Models;

use App\Domain\Expenses\Models\PettyCashAdvance;
use App\Domain\Organisations\Models\Organisation;
use App\Domain\Wallets\Models\Wallet;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PettyCashAdvance>
 */
class PettyCashAdvanceFactory extends Factory
{
    protected $model = PettyCashAdvance::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organisation_id' => Organisation::factory(), 'advance_number' => fake()->unique()->bothify('ORG-PCA-2026-######'),
            'custodian_user_id' => User::factory(), 'recipient_user_id' => User::factory(),
            'petty_cash_wallet_id' => Wallet::factory(), 'funding_wallet_id' => Wallet::factory(),
            'requested_amount_minor' => 10000, 'currency_code' => 'GHS', 'purpose' => fake()->sentence(),
            'status' => 'draft', 'requested_at' => now(), 'requested_by_user_id' => User::factory(),
        ];
    }
}
