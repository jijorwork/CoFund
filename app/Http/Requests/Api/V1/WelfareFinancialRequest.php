<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class WelfareFinancialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return ['source_wallet_uuid' => ['sometimes', 'required', 'uuid'], 'beneficiary_uuid' => ['nullable', 'uuid'], 'amount_minor' => ['sometimes', 'required', 'integer', 'min:1'], 'payment_method' => ['sometimes', 'required', 'in:cash,bank,mobile_money,internal_transfer'], 'payment_date' => ['sometimes', 'required', 'date'], 'recipient_type' => ['sometimes', 'required', 'in:member,beneficiary,external_recipient,service_provider,estate_representative'], 'recipient_name' => ['sometimes', 'required', 'string', 'max:255'], 'reason' => ['sometimes', 'required', 'string', 'max:5000'], 'approved_benefit_minor' => ['sometimes', 'required', 'integer', 'min:1'], 'date' => ['sometimes', 'required', 'date']];
    }

    public function idempotencyKey(): string
    {
        return trim((string) $this->header('Idempotency-Key')) ?: throw ValidationException::withMessages(['Idempotency-Key' => ['The Idempotency-Key header is required.']]);
    }
}
