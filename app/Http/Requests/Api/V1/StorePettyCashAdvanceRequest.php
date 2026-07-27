<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StorePettyCashAdvanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['branch_uuid' => ['nullable', 'uuid'], 'custodian_user_uuid' => ['required', 'uuid'], 'recipient_user_uuid' => ['required', 'uuid'], 'petty_cash_wallet_uuid' => ['required', 'uuid'], 'funding_wallet_uuid' => ['required', 'uuid'], 'requested_amount_minor' => ['required', 'integer', 'min:1'], 'purpose' => ['required', 'string', 'max:5000'], 'retirement_due_date' => ['nullable', 'date']];
    }
}
