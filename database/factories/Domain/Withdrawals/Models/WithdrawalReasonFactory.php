<?php

namespace Database\Factories\Domain\Withdrawals\Models;

use App\Domain\Organisations\Models\Organisation;
use App\Domain\Withdrawals\Models\WithdrawalReason;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WithdrawalReason> */
class WithdrawalReasonFactory extends Factory
{
    protected $model = WithdrawalReason::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['organisation_id' => Organisation::factory(), 'code' => fake()->unique()->bothify('RSN-####'), 'name' => fake()->words(2, true), 'category' => 'other', 'status' => 'active', 'created_by_user_id' => User::factory()];
    }
}
