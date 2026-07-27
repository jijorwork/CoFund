<?php

namespace Database\Factories\Domain\Withdrawals\Models;

use App\Domain\Membership\Models\Member;
use App\Domain\Wallets\Models\Wallet;
use App\Domain\Withdrawals\Models\WithdrawalProduct;
use App\Domain\Withdrawals\Models\WithdrawalRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WithdrawalRequest> */
class WithdrawalRequestFactory extends Factory
{
    protected $model = WithdrawalRequest::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['organisation_id' => fn (array $attributes) => Member::query()->whereKey($attributes['member_id'])->value('organisation_id'), 'request_number' => fake()->unique()->bothify('WDR-####'), 'member_id' => Member::factory(), 'withdrawal_product_id' => WithdrawalProduct::factory(), 'savings_wallet_id' => Wallet::factory(), 'requested_amount_minor' => 10000, 'currency_code' => 'GHS', 'request_date' => today(), 'created_by_user_id' => User::factory()];
    }
}
