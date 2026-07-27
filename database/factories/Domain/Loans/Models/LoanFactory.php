<?php

namespace Database\Factories\Domain\Loans\Models;

use App\Domain\Loans\Models\Loan;
use App\Domain\Loans\Models\LoanApplication;
use App\Domain\Loans\Models\LoanOffer;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Loan> */
class LoanFactory extends Factory
{
    protected $model = Loan::class;

    /** @return array<string,mixed> */
    public function definition(): array
    {
        return ['loan_offer_id' => LoanOffer::factory(), 'loan_application_id' => fn (array $attributes): int => (int) LoanOffer::query()->whereKey($attributes['loan_offer_id'])->value('loan_application_id'), 'organisation_id' => fn (array $attributes): int => (int) LoanOffer::query()->whereKey($attributes['loan_offer_id'])->value('organisation_id'), 'branch_id' => fn (array $attributes): int => (int) LoanApplication::query()->whereKey($attributes['loan_application_id'])->value('branch_id'), 'member_id' => fn (array $attributes): int => (int) LoanApplication::query()->whereKey($attributes['loan_application_id'])->value('member_id'), 'loan_product_id' => fn (array $attributes): int => (int) LoanApplication::query()->whereKey($attributes['loan_application_id'])->value('loan_product_id'), 'loan_number' => fake()->unique()->bothify('TEST-LON-2026-######'), 'principal_minor' => 100000, 'contractual_interest_minor' => 6000, 'total_repayable_minor' => 106000, 'currency_code' => 'GHS', 'first_repayment_date' => now()->addMonth(), 'maturity_date' => now()->addMonths(6), 'repayment_frequency' => 'monthly', 'interest_method' => 'flat', 'interest_rate_basis_points' => 100, 'outstanding_principal_minor' => 100000, 'outstanding_interest_minor' => 6000];
    }
}
