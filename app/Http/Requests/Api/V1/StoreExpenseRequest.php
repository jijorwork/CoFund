<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['branch_uuid' => ['nullable', 'uuid'], 'category_uuid' => ['required', 'uuid'], 'title' => ['required', 'string', 'max:255'], 'purpose' => ['required', 'string', 'max:5000'], 'beneficiary_type' => ['required', 'in:requester,staff,vendor,organisation,other'], 'beneficiary_name' => ['nullable', 'string', 'max:255'], 'needed_by_date' => ['nullable', 'date'], 'expense_date' => ['nullable', 'date'], 'payment_method_preference' => ['nullable', 'string', 'max:50'], 'requested_source_wallet_uuid' => ['nullable', 'uuid'], 'notes' => ['nullable', 'string', 'max:3000'], 'items' => ['required', 'array', 'min:1'], 'items.*.description' => ['required', 'string', 'max:500'], 'items.*.quantity' => ['required', 'integer', 'min:1'], 'items.*.unit_amount_minor' => ['required', 'integer', 'min:1'], 'items.*.category_uuid' => ['nullable', 'uuid'], 'items.*.ledger_account_uuid' => ['nullable', 'uuid']];
    }
}
