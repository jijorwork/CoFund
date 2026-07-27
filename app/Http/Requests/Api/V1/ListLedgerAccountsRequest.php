<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Accounting\Enums\AccountCategory;
use App\Domain\Accounting\Enums\AccountPurpose;
use App\Domain\Accounting\Enums\LedgerAccountStatus;
use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListLedgerAccountsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(RoleAuthorization::class)->can(FoundationPermission::LedgerAccountsView->value);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'filter' => ['sometimes', 'array:category,purpose,status,branch_uuid,currency_code'],
            'filter.category' => ['sometimes', Rule::enum(AccountCategory::class)],
            'filter.purpose' => ['sometimes', Rule::enum(AccountPurpose::class)],
            'filter.status' => ['sometimes', Rule::enum(LedgerAccountStatus::class)],
            'filter.branch_uuid' => ['sometimes', 'uuid'],
            'filter.currency_code' => ['sometimes', 'string', 'size:3'],
            'sort' => ['sometimes', Rule::in(['code', '-code', 'name', '-name', 'category', '-category', 'created_at', '-created_at'])],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
