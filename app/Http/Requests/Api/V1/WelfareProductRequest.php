<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class WelfareProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        $presence = $this->isMethod('post') ? 'required' : 'sometimes';

        return ['code' => [$presence, 'string', 'max:50'], 'name' => [$presence, 'string', 'max:255'], 'description' => ['sometimes', 'nullable', 'string'], 'product_type' => [$presence, 'string', 'in:bereavement,medical,maternity,education,emergency,disability,retirement,disaster,hardship,other'], 'currency_code' => [$presence, 'string', 'size:3'], 'eligibility_basis' => ['sometimes', 'string', 'max:40'], 'minimum_membership_days' => ['sometimes', 'integer', 'min:0'], 'minimum_contribution_count' => ['sometimes', 'integer', 'min:0'], 'minimum_contribution_amount_minor' => ['sometimes', 'integer', 'min:0'], 'require_active_contribution_enrolment' => ['sometimes', 'boolean'], 'require_good_standing' => ['sometimes', 'boolean'], 'block_member_with_arrears' => ['sometimes', 'boolean'], 'block_member_with_overdue_loan' => ['sometimes', 'boolean'], 'waiting_period_count' => ['sometimes', 'integer', 'min:0'], 'waiting_period_unit' => ['nullable', 'required_if:waiting_period_count,1', 'in:days,weeks,months'], 'benefit_calculation_type' => [$presence, 'in:fixed,percentage_of_contributions,percentage_of_savings,percentage_of_declared_cost,manual_with_limits'], 'fixed_benefit_minor' => ['nullable', 'integer', 'min:1'], 'benefit_percentage_basis_points' => ['nullable', 'integer', 'between:0,10000'], 'benefit_percentage_base' => ['nullable', 'in:qualifying_contributions,qualifying_savings,approved_expense,declared_cost'], 'minimum_benefit_minor' => ['nullable', 'integer', 'min:0'], 'maximum_benefit_minor' => ['nullable', 'integer', 'gte:minimum_benefit_minor'], 'maximum_claims_per_period' => ['nullable', 'integer', 'min:1'], 'claim_limit_period' => ['nullable', 'in:month,quarter,year,lifetime'], 'maximum_total_benefit_per_period_minor' => ['nullable', 'integer', 'min:1'], 'require_beneficiary' => ['sometimes', 'boolean'], 'allow_member_as_recipient' => ['sometimes', 'boolean'], 'allow_external_recipient' => ['sometimes', 'boolean'], 'require_documents' => ['sometimes', 'boolean'], 'require_verification' => ['sometimes', 'boolean'], 'require_primary_approval' => ['sometimes', 'boolean'], 'require_secondary_approval' => ['sometimes', 'boolean'], 'secondary_approval_threshold_minor' => ['nullable', 'integer', 'min:1'], 'partial_payment_allowed' => ['sometimes', 'boolean'], 'payment_deadline_days' => ['nullable', 'integer', 'min:1'], 'required_document_types' => ['nullable', 'array'], 'required_document_types.*' => ['string', 'max:50'], 'branch_uuid' => ['nullable', 'uuid'], 'group_uuid' => ['nullable', 'uuid']];
    }
}
