<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Expenses\Enums\ExpensePaymentMethod;
use App\Domain\Expenses\Enums\ExpensePaymentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PayExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['source_wallet_uuid' => ['required', 'uuid'], 'amount_minor' => ['required', 'integer', 'min:1'], 'payment_type' => ['required', Rule::enum(ExpensePaymentType::class)], 'payment_method' => ['required', Rule::enum(ExpensePaymentMethod::class)], 'payment_date' => ['required', 'date'], 'payee_name' => ['required', 'string', 'max:255']];
    }
}
