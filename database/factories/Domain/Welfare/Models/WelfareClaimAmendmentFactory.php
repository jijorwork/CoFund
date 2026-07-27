<?php

namespace Database\Factories\Domain\Welfare\Models;

use App\Domain\Welfare\Models\WelfareClaim;
use App\Domain\Welfare\Models\WelfareClaimAmendment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WelfareClaimAmendment> */
class WelfareClaimAmendmentFactory extends Factory
{
    protected $model = WelfareClaimAmendment::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['organisation_id' => fn (array $attributes) => WelfareClaim::query()->whereKey($attributes['welfare_claim_id'])->value('organisation_id'), 'welfare_claim_id' => WelfareClaim::factory(), 'amendment_number' => fake()->unique()->bothify('WAM-####'), 'amendment_type' => 'beneficiary', 'reason' => fake()->sentence(), 'original_values' => [], 'proposed_values' => [], 'status' => 'requested', 'requested_by_user_id' => User::factory()];
    }
}
