<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use Illuminate\Foundation\Http\FormRequest;

class GroupMembershipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(RoleAuthorization::class)->can(FoundationPermission::GroupsManageMembers->value);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'member_uuid' => [
                $this->route()->getActionMethod() === 'addMember' ? 'required' : 'sometimes',
                'uuid',
            ],
        ];
    }
}
