<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class LoanApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        $presence = $this->isMethod('post') ? 'required' : 'sometimes';

        return ['member_uuid' => [$presence, 'uuid'], 'product_uuid' => [$presence, 'uuid'], 'requested_principal_minor' => [$presence, 'integer', 'min:1'], 'requested_term' => [$presence, 'integer', 'min:1'], 'purpose' => [$presence, 'string', 'max:5000'], 'purpose_category' => ['sometimes', 'nullable', 'string', 'max:80'], 'income_source' => ['sometimes', 'nullable', 'string', 'max:255'], 'declared_monthly_income_minor' => ['sometimes', 'integer', 'min:0'], 'declared_monthly_expenses_minor' => ['sometimes', 'integer', 'min:0'], 'existing_debt_minor' => ['sometimes', 'integer', 'min:0'], 'preferred_disbursement_method' => [$presence, 'string', 'in:cash,bank,mobile_money,internal_wallet']];
    }
}
