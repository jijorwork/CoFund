<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Collections\Enums\ReceiptStatus;
use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListReceiptsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(RoleAuthorization::class)->can(FoundationPermission::ReceiptsView->value);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'filter' => ['sometimes', 'array'],
            'filter.receipt_number' => ['sometimes', 'string', 'max:255'],
            'filter.member_uuid' => ['sometimes', 'uuid'],
            'filter.payment_uuid' => ['sometimes', 'uuid'],
            'filter.status' => ['sometimes', Rule::enum(ReceiptStatus::class)],
            'filter.issued_from' => ['sometimes', 'date'],
            'filter.issued_to' => ['sometimes', 'date'],
        ];
    }
}
