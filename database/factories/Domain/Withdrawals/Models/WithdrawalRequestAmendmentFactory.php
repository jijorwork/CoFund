<?php

namespace Database\Factories\Domain\Withdrawals\Models;

use App\Domain\Withdrawals\Models\WithdrawalRequest;
use App\Domain\Withdrawals\Models\WithdrawalRequestAmendment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WithdrawalRequestAmendment> */
class WithdrawalRequestAmendmentFactory extends Factory
{
    protected $model = WithdrawalRequestAmendment::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['organisation_id' => fn (array $attributes) => WithdrawalRequest::query()->whereKey($attributes['withdrawal_request_id'])->value('organisation_id'), 'withdrawal_request_id' => WithdrawalRequest::factory(), 'amendment_number' => fake()->unique()->bothify('WAM-####'), 'amendment_type' => 'approved_amount_reduction', 'reason' => fake()->sentence(), 'original_values' => [], 'proposed_values' => [], 'status' => 'requested', 'requested_by_user_id' => User::factory()];
    }
}
