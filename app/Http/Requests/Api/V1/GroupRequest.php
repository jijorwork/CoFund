<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Membership\Enums\GroupStatus;
use App\Domain\Membership\Enums\GroupType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        $permission = $this->isMethod('post')
            ? FoundationPermission::GroupsCreate
            : FoundationPermission::GroupsUpdate;

        return app(RoleAuthorization::class)->can($permission->value);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'branch_uuid' => ['sometimes', 'nullable', 'uuid'],
            'name' => [$required, 'string', 'max:255'],
            'code' => [$required, 'string', 'max:100'],
            'group_type' => [$required, Rule::enum(GroupType::class)],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'status' => ['sometimes', Rule::enum(GroupStatus::class)],
        ];
    }
}
