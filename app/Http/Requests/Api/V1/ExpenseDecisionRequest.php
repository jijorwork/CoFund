<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class ExpenseDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['amount_minor' => ['sometimes', 'integer', 'min:1'], 'returned_cash_minor' => ['sometimes', 'integer', 'min:0'], 'reason' => ['sometimes', 'string', 'max:3000']];
    }
}
