<?php

namespace Database\Factories\Domain\Loans\Models;

use App\Domain\Loans\Enums\LoanApplicationStatus;
use App\Domain\Loans\Models\LoanApplication;
use App\Domain\Loans\Models\LoanProduct;
use App\Domain\Membership\Models\Member;
use App\Domain\Organisations\Models\Branch;
use App\Domain\Organisations\Models\Organisation;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LoanApplication> */
class LoanApplicationFactory extends Factory
{
    protected $model = LoanApplication::class;

    /** @return array<string,mixed> */
    public function definition(): array
    {
        return ['organisation_id' => Organisation::factory(), 'branch_id' => fn (array $attributes) => Branch::factory()->state(['organisation_id' => $attributes['organisation_id']]), 'loan_product_id' => fn (array $attributes) => LoanProduct::factory()->state(['organisation_id' => $attributes['organisation_id']]), 'member_id' => fn (array $attributes) => Member::factory()->state(['organisation_id' => $attributes['organisation_id'], 'branch_id' => $attributes['branch_id']]), 'application_number' => fake()->unique()->bothify('TEST-LAP-2026-######'), 'requested_principal_minor' => 100000, 'requested_term' => 6, 'repayment_frequency' => 'monthly', 'purpose' => 'Working capital', 'preferred_disbursement_method' => 'bank', 'application_date' => now()->toDateString(), 'workflow_status' => LoanApplicationStatus::Draft];
    }
}
