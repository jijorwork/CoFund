<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StorePettyCashRetirementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['returned_cash_minor' => ['required', 'integer', 'min:0'], 'items' => ['required', 'array', 'min:1'], 'items.*.category_uuid' => ['required', 'uuid'], 'items.*.ledger_account_uuid' => ['required', 'uuid'], 'items.*.description' => ['required', 'string', 'max:500'], 'items.*.amount_minor' => ['required', 'integer', 'min:1'], 'items.*.expense_date' => ['required', 'date']];
    }
}
