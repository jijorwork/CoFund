<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class LoanDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return ['approved_principal_minor' => ['sometimes', 'integer', 'min:1'], 'approved_term' => ['sometimes', 'integer', 'min:1'], 'reason' => ['nullable', 'string', 'max:5000'], 'override' => ['boolean'], 'override_reason' => ['nullable', 'required_if:override,true', 'string', 'max:5000']];
    }
}
