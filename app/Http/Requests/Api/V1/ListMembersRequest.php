<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Membership\Enums\MemberStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListMembersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(RoleAuthorization::class)->can(FoundationPermission::MembersView->value);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'filter' => ['sometimes', 'array:membership_number,name,phone,email,branch_uuid,status,group_uuid,joined_from,joined_to'],
            'filter.membership_number' => ['sometimes', 'string', 'max:255'],
            'filter.name' => ['sometimes', 'string', 'max:255'],
            'filter.phone' => ['sometimes', 'string', 'max:30'],
            'filter.email' => ['sometimes', 'email', 'max:255'],
            'filter.branch_uuid' => ['sometimes', 'uuid'],
            'filter.group_uuid' => ['sometimes', 'uuid'],
            'filter.status' => ['sometimes', Rule::enum(MemberStatus::class)],
            'filter.joined_from' => ['sometimes', 'date'],
            'filter.joined_to' => ['sometimes', 'date', 'after_or_equal:filter.joined_from'],
            'sort' => ['sometimes', Rule::in([
                'membership_number', '-membership_number', 'first_name', '-first_name',
                'last_name', '-last_name', 'joined_at', '-joined_at',
                'created_at', '-created_at',
            ])],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
