<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Expenses\Enums\ExpensePaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExpenseCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['code' => ['required', 'string', 'max:50'], 'name' => ['required', 'string', 'max:150'], 'description' => ['nullable', 'string', 'max:2000'], 'parent_uuid' => ['nullable', 'uuid'], 'default_ledger_account_uuid' => ['required', 'uuid'], 'requires_receipt' => ['sometimes', 'boolean'], 'requires_preapproval' => ['sometimes', 'boolean'], 'requires_secondary_approval' => ['sometimes', 'boolean'], 'maximum_without_secondary_approval_minor' => ['nullable', 'integer', 'min:0'], 'allowed_payment_methods' => ['nullable', 'array'], 'allowed_payment_methods.*' => [Rule::enum(ExpensePaymentMethod::class)]];
    }
}
