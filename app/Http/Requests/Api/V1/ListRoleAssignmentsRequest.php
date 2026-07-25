<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Enums\RoleAssignmentStatus;
use App\Domain\Identity\Services\RoleAuthorization;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListRoleAssignmentsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return app(RoleAuthorization::class)->can(FoundationPermission::RolesView->value);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'filter.user_uuid' => ['sometimes', 'uuid'],
            'filter.role_name' => ['sometimes', 'string', 'max:255'],
            'filter.branch_uuid' => ['sometimes', 'uuid'],
            'filter.status' => ['sometimes', Rule::enum(RoleAssignmentStatus::class)],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
