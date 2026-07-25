<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Membership\Enums\MemberContactType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MemberContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(RoleAuthorization::class)->can(FoundationPermission::MembersManageContacts->value);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        if ($this->route()->getActionMethod() === 'deactivate') {
            return [];
        }

        $required = $this->route()->getActionMethod() === 'store' ? 'required' : 'sometimes';

        return [
            'contact_type' => [$required, Rule::enum(MemberContactType::class)],
            'full_name' => [$required, 'string', 'max:255'],
            'relationship' => [$required, 'string', 'max:100'],
            'phone' => [$required, 'string', 'max:30'],
            'alternate_phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'address' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'national_id_type' => ['sometimes', 'nullable', 'string', 'max:100'],
            'national_id_number' => ['sometimes', 'nullable', 'string', 'max:255'],
            'percentage_entitlement' => ['sometimes', 'nullable', 'decimal:0,2', 'between:0,100'],
            'is_primary' => ['sometimes', 'boolean'],
            'is_emergency_contact' => ['sometimes', 'boolean'],
        ];
    }
}
