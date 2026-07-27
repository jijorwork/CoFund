<?php

namespace Database\Factories\Domain\Withdrawals\Models;

use App\Domain\Membership\Models\Member;
use App\Domain\Wallets\Models\Wallet;
use App\Domain\Withdrawals\Models\SavingsHold;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SavingsHold> */
class SavingsHoldFactory extends Factory
{
    protected $model = SavingsHold::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['organisation_id' => fn (array $attributes) => Member::query()->whereKey($attributes['member_id'])->value('organisation_id'), 'member_id' => Member::factory(), 'wallet_id' => Wallet::factory(), 'hold_number' => fake()->unique()->bothify('SHD-####'), 'hold_type' => 'manual', 'source_type' => 'manual', 'amount_minor' => 10000, 'currency_code' => 'GHS', 'status' => 'active', 'reason' => fake()->sentence(), 'starts_at' => now(), 'created_by_user_id' => User::factory()];
    }
}
