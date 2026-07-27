<?php

namespace Database\Factories\Domain\Welfare\Models;

use App\Domain\Welfare\Models\WelfareClaim;
use App\Domain\Welfare\Models\WelfareClaimBeneficiary;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WelfareClaimBeneficiary> */
class WelfareClaimBeneficiaryFactory extends Factory
{
    protected $model = WelfareClaimBeneficiary::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['organisation_id' => fn (array $attributes) => WelfareClaim::query()->whereKey($attributes['welfare_claim_id'])->value('organisation_id'), 'welfare_claim_id' => WelfareClaim::factory(), 'beneficiary_type' => 'external', 'name' => fake()->name(), 'allocation_basis_points' => 10000, 'is_primary' => true, 'payment_eligible' => true, 'status' => 'active'];
    }
}
