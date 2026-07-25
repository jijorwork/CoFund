<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Membership\Enums\GroupStatus;
use App\Domain\Membership\Enums\GroupType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListGroupsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(RoleAuthorization::class)->can(FoundationPermission::GroupsView->value);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'filter' => ['sometimes', 'array:status,group_type'],
            'filter.status' => ['sometimes', Rule::enum(GroupStatus::class)],
            'filter.group_type' => ['sometimes', Rule::enum(GroupType::class)],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
