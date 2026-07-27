<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Loans\Enums\InterestMethod;
use App\Domain\Loans\Enums\ProcessingFeeTreatment;
use App\Domain\Loans\Enums\ProcessingFeeType;
use App\Domain\Loans\Enums\RepaymentFrequency;
use App\Domain\Loans\Enums\TermUnit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LoanProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return ['code' => ['required', 'string', 'max:40'], 'name' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string'], 'branch_uuid' => ['nullable', 'uuid'], 'group_uuid' => ['nullable', 'uuid'], 'minimum_amount_minor' => ['required', 'integer', 'min:1'], 'maximum_amount_minor' => ['required', 'integer', 'gte:minimum_amount_minor'], 'minimum_term' => ['required', 'integer', 'min:1'], 'maximum_term' => ['required', 'integer', 'gte:minimum_term'], 'term_unit' => ['required', Rule::enum(TermUnit::class)], 'repayment_frequency' => ['required', Rule::enum(RepaymentFrequency::class)], 'interest_method' => ['required', Rule::enum(InterestMethod::class)], 'interest_rate_basis_points' => ['required', 'integer', 'min:0', 'max:100000'], 'grace_period_days' => ['integer', 'min:0', 'max:365'], 'minimum_membership_days' => ['integer', 'min:0'], 'minimum_contribution_count' => ['integer', 'min:0'], 'minimum_contribution_value_minor' => ['integer', 'min:0'], 'maximum_loan_to_savings_basis_points' => ['nullable', 'integer', 'min:1'], 'maximum_active_loans' => ['integer', 'min:1'], 'blocks_overdue_borrowers' => ['boolean'], 'required_guarantor_count' => ['integer', 'min:0'], 'guarantee_limit_basis_points' => ['integer', 'min:0'], 'requires_collateral' => ['boolean'], 'processing_fee_type' => ['required', Rule::enum(ProcessingFeeType::class)], 'processing_fee_fixed_minor' => ['integer', 'min:0'], 'processing_fee_basis_points' => ['integer', 'min:0'], 'processing_fee_treatment' => ['required', Rule::enum(ProcessingFeeTreatment::class)], 'allows_partial_repayment' => ['boolean'], 'allows_advance_payment' => ['boolean'], 'secondary_approval_threshold_minor' => ['nullable', 'integer', 'min:1'], 'non_performing_days' => ['integer', 'min:1']];
    }
}
