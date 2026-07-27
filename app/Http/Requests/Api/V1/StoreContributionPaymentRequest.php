<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Collections\Enums\ContributionPaymentMethod;
use App\Domain\Collections\Enums\PaymentAllocationStrategy;
use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContributionPaymentRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['idempotency_key' => $this->header('Idempotency-Key')]);
    }

    public function authorize(): bool
    {
        return app(RoleAuthorization::class)->can(FoundationPermission::ContributionPaymentsCollect->value);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'string', 'max:200'],
            'member_uuid' => ['required', 'uuid'],
            'enrolment_uuid' => ['sometimes', 'nullable', 'uuid'],
            'plan_uuid' => ['sometimes', 'nullable', 'uuid'],
            'amount_minor' => ['required', 'integer', 'min:1'],
            'currency_code' => ['required', 'string', 'size:3'],
            'payment_method' => ['required', Rule::enum(ContributionPaymentMethod::class)],
            'payment_date' => ['required', 'date', 'before_or_equal:today'],
            'source_wallet_uuid' => ['required', 'uuid'],
            'allocation_strategy' => ['required', Rule::enum(PaymentAllocationStrategy::class)],
            'allocations' => ['sometimes', 'array'],
            'allocations.*' => ['required', 'array:obligation_uuid,amount_minor'],
            'allocations.*.obligation_uuid' => ['required', 'uuid', 'distinct'],
            'allocations.*.amount_minor' => ['required', 'integer', 'min:1'],
            'external_reference' => ['sometimes', 'nullable', 'string', 'max:255'],
            'payer_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'payer_phone' => ['sometimes', 'nullable', 'string', 'max:32'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
