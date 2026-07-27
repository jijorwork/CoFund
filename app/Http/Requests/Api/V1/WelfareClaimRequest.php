<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class WelfareClaimRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        $presence = $this->isMethod('post') ? 'required' : 'sometimes';

        return ['enrolment_uuid' => [$presence, 'uuid'], 'event_uuid' => [$presence, 'uuid'], 'claimant_type' => [$presence, 'in:member,beneficiary,guardian,representative,organisation'], 'claimant_member_uuid' => ['nullable', 'uuid'], 'claimant_name' => ['nullable', 'string', 'max:255'], 'claimant_contact' => ['nullable', 'string', 'max:255'], 'requested_benefit_minor' => [$presence, 'integer', 'min:1'], 'declared_cost_minor' => ['nullable', 'integer', 'min:1'], 'claim_date' => [$presence, 'date']];
    }
}
