<?php

namespace Database\Factories\Domain\Loans\Models;

use App\Domain\Loans\Enums\InterestMethod;
use App\Domain\Loans\Enums\LoanProductStatus;
use App\Domain\Loans\Enums\ProcessingFeeTreatment;
use App\Domain\Loans\Enums\ProcessingFeeType;
use App\Domain\Loans\Enums\RepaymentFrequency;
use App\Domain\Loans\Enums\TermUnit;
use App\Domain\Loans\Models\LoanProduct;
use App\Domain\Organisations\Models\Organisation;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LoanProduct> */
class LoanProductFactory extends Factory
{
    protected $model = LoanProduct::class;

    /** @return array<string,mixed> */
    public function definition(): array
    {
        return ['organisation_id' => Organisation::factory(), 'code' => fake()->unique()->bothify('LOAN-####'), 'name' => fake()->words(2, true), 'currency_code' => 'GHS', 'minimum_amount_minor' => 10000, 'maximum_amount_minor' => 1000000, 'minimum_term' => 1, 'maximum_term' => 24, 'term_unit' => TermUnit::Months, 'repayment_frequency' => RepaymentFrequency::Monthly, 'interest_method' => InterestMethod::Flat, 'interest_rate_basis_points' => 100, 'processing_fee_type' => ProcessingFeeType::None, 'processing_fee_treatment' => ProcessingFeeTreatment::Deducted, 'status' => LoanProductStatus::Active, 'activated_at' => now()];
    }
}
