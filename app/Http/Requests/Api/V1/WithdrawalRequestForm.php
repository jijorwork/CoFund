<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class WithdrawalRequestForm extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['member_uuid' => ['sometimes', 'required', 'uuid'], 'product_uuid' => ['sometimes', 'required', 'uuid'], 'reason_uuid' => ['nullable', 'uuid'], 'savings_wallet_uuid' => ['sometimes', 'required', 'uuid'], 'destination_wallet_uuid' => ['sometimes', 'required', 'uuid'], 'requested_amount_minor' => ['sometimes', 'required', 'integer', 'min:1'], 'amount_minor' => ['sometimes', 'required', 'integer', 'min:1'], 'approved_amount_minor' => ['sometimes', 'required', 'integer', 'min:1'], 'reason_text' => ['nullable', 'string', 'max:5000'], 'reason' => ['nullable', 'string', 'max:5000'], 'is_full_balance' => ['boolean'], 'payment_method' => ['sometimes', 'required', 'in:cash,bank,mobile_money,internal_transfer'], 'payout_date' => ['sometimes', 'required', 'date'], 'recipient_type' => ['sometimes', 'required', 'in:member,registered_beneficiary,authorised_representative,external_recipient'], 'recipient_name' => ['sometimes', 'required', 'string', 'max:255']];
    }

    public function idempotencyKey(): string
    {
        return trim((string) $this->header('Idempotency-Key')) ?: throw ValidationException::withMessages(['Idempotency-Key' => ['The Idempotency-Key header is required.']]);
    }
}
