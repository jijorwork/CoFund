<?php

namespace Database\Factories\Domain\Welfare\Models;

use App\Domain\Accounting\Models\FinancialTransaction;
use App\Domain\Welfare\Models\WelfareClaim;
use App\Domain\Welfare\Models\WelfareClaimLiability;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WelfareClaimLiability> */
class WelfareClaimLiabilityFactory extends Factory
{
    protected $model = WelfareClaimLiability::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['organisation_id' => fn (array $attributes) => WelfareClaim::query()->whereKey($attributes['welfare_claim_id'])->value('organisation_id'), 'welfare_claim_id' => WelfareClaim::factory(), 'liability_number' => fake()->unique()->bothify('WLI-####'), 'approved_benefit_minor' => 10000, 'currency_code' => 'GHS', 'status' => 'recognised', 'active_liability_key' => fake()->uuid(), 'financial_transaction_id' => FinancialTransaction::factory(), 'recognised_at' => now(), 'recognised_by_user_id' => User::factory(), 'idempotency_key' => fake()->uuid(), 'request_fingerprint' => hash('sha256', fake()->uuid())];
    }
}
