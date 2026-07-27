<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class LoanFinancialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return ['source_wallet_uuid' => ['sometimes', 'required', 'uuid'], 'amount_minor' => ['sometimes', 'required', 'integer', 'min:1'], 'method' => ['sometimes', 'required', 'string', 'in:cash,bank,mobile_money,internal_wallet'], 'destination_type' => ['sometimes', 'required', 'string', 'in:cash,bank,mobile_money,internal_wallet'], 'destination_reference' => ['nullable', 'string', 'max:255'], 'date' => ['required', 'date'], 'reason' => ['sometimes', 'required', 'string', 'max:5000']];
    }

    public function idempotencyKey(): string
    {
        return trim((string) $this->header('Idempotency-Key')) ?: throw ValidationException::withMessages(['Idempotency-Key' => ['The Idempotency-Key header is required.']]);
    }
}
