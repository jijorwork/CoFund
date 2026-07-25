<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Enums\SystemRole;
use App\Domain\Identity\Services\RoleAuthorization;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRoleAssignmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return app(RoleAuthorization::class)->can(FoundationPermission::RolesAssign->value);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'user_uuid' => ['required', 'uuid'],
            'role_name' => [
                'required',
                Rule::enum(SystemRole::class)->only(
                    array_values(array_filter(
                        SystemRole::cases(),
                        fn (SystemRole $role): bool => $role->isOrganisationAssignable(),
                    )),
                ),
            ],
            'branch_uuid' => ['nullable', 'uuid'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
        ];
    }
}
