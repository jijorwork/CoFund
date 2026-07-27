<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Accounting\Enums\AccountCategory;
use App\Domain\Accounting\Enums\AccountPurpose;
use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LedgerAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        $permission = $this->isMethod('post')
            ? FoundationPermission::LedgerAccountsCreate
            : FoundationPermission::LedgerAccountsUpdate;

        return app(RoleAuthorization::class)->can($permission->value);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'branch_uuid' => ['sometimes', 'nullable', 'uuid'],
            'parent_uuid' => ['sometimes', 'nullable', 'uuid'],
            'code' => [$required, 'string', 'max:100'],
            'name' => [$required, 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'category' => [$required, Rule::enum(AccountCategory::class)],
            'purpose' => [$required, Rule::enum(AccountPurpose::class)],
            'currency_code' => ['sometimes', 'string', 'size:3'],
            'allows_direct_posting' => ['sometimes', 'boolean'],
        ];
    }
}
