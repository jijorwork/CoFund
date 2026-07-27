<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Collections\Enums\ContributionPaymentMethod;
use App\Domain\Collections\Enums\ContributionPaymentStatus;
use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListContributionPaymentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(RoleAuthorization::class)->can(FoundationPermission::ContributionPaymentsView->value);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'filter' => ['sometimes', 'array'],
            'filter.payment_number' => ['sometimes', 'string', 'max:255'],
            'filter.member_uuid' => ['sometimes', 'uuid'],
            'filter.enrolment_uuid' => ['sometimes', 'uuid'],
            'filter.payment_method' => ['sometimes', Rule::enum(ContributionPaymentMethod::class)],
            'filter.status' => ['sometimes', Rule::enum(ContributionPaymentStatus::class)],
            'filter.branch_uuid' => ['sometimes', 'uuid'],
            'filter.collector_user_uuid' => ['sometimes', 'uuid'],
            'filter.source_wallet_uuid' => ['sometimes', 'uuid'],
            'filter.payment_date_from' => ['sometimes', 'date'],
            'filter.payment_date_to' => ['sometimes', 'date'],
            'filter.external_reference' => ['sometimes', 'string', 'max:255'],
            'sort' => ['sometimes', Rule::in([
                'payment_date', '-payment_date', 'received_at', '-received_at',
                'payment_number', '-payment_number', 'amount', '-amount',
                'created_at', '-created_at',
            ])],
        ];
    }
}
