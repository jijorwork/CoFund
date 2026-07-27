<?php

namespace Database\Factories\Domain\Loans\Models;

use App\Domain\Loans\Models\LoanApplication;
use App\Domain\Loans\Models\LoanOffer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LoanOffer> */
class LoanOfferFactory extends Factory
{
    protected $model = LoanOffer::class;

    /** @return array<string,mixed> */
    public function definition(): array
    {
        return ['loan_application_id' => LoanApplication::factory(), 'organisation_id' => fn (array $attributes): int => LoanApplication::query()->whereKey($attributes['loan_application_id'])->value('organisation_id'), 'offer_number' => fake()->unique()->bothify('TEST-LOF-2026-######'), 'version' => 1, 'principal_minor' => 100000, 'interest_minor' => 6000, 'fees_minor' => 0, 'total_repayable_minor' => 106000, 'term' => 6, 'repayment_frequency' => 'monthly', 'interest_method' => 'flat', 'interest_rate_basis_points' => 100, 'first_repayment_date' => now()->addMonth(), 'maturity_date' => now()->addMonths(6), 'issued_at' => now(), 'expires_at' => now()->addWeek(), 'issued_by_user_id' => User::factory()];
    }
}
