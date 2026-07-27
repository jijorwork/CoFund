<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class WithdrawalProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['code' => ['required', 'string', 'max:50'], 'name' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string'], 'product_type' => ['required', 'string', 'max:40'], 'currency_code' => ['required', 'string', 'size:3'], 'eligible_savings_wallet_type' => ['required', 'in:member_savings'], 'minimum_withdrawal_minor' => ['nullable', 'integer', 'min:1'], 'maximum_withdrawal_minor' => ['nullable', 'integer', 'gte:minimum_withdrawal_minor'], 'minimum_remaining_balance_minor' => ['nullable', 'integer', 'min:0'], 'minimum_remaining_balance_basis_points' => ['nullable', 'integer', 'between:0,10000'], 'maximum_withdrawal_percentage_basis_points' => ['nullable', 'integer', 'between:1,10000'], 'maximum_withdrawals_per_period' => ['nullable', 'integer', 'min:1'], 'withdrawal_limit_period' => ['nullable', 'in:month,quarter,year,lifetime'], 'maximum_total_withdrawal_per_period_minor' => ['nullable', 'integer', 'min:1'], 'minimum_membership_days' => ['nullable', 'integer', 'min:0'], 'minimum_savings_age_days' => ['nullable', 'integer', 'min:0'], 'minimum_contribution_count' => ['nullable', 'integer', 'min:0'], 'require_active_contribution_enrolment' => ['boolean'], 'notice_period_count' => ['integer', 'min:0'], 'notice_period_unit' => ['nullable', 'in:days,weeks,months'], 'allow_partial_payout' => ['boolean'], 'allow_full_balance_withdrawal' => ['boolean'], 'allow_during_member_exit' => ['boolean'], 'require_reason' => ['boolean'], 'require_documents' => ['boolean'], 'loan_restriction_policy' => ['required', 'string', 'max:60'], 'guarantor_restriction_policy' => ['required', 'string', 'max:60'], 'welfare_restriction_policy' => ['required', 'string', 'max:60'], 'fee_type' => ['required', 'in:none,fixed,percentage'], 'fee_value' => ['nullable', 'integer', 'min:0'], 'fee_treatment' => ['required', 'in:deducted_from_payout'], 'require_primary_approval' => ['boolean'], 'require_secondary_approval' => ['boolean'], 'secondary_approval_threshold_minor' => ['nullable', 'integer', 'min:1'], 'payout_deadline_days' => ['nullable', 'integer', 'min:1']];
    }
}
