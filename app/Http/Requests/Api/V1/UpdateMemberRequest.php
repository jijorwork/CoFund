<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Membership\Enums\MemberGender;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(RoleAuthorization::class)->can(FoundationPermission::MembersUpdate->value);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'branch_uuid' => ['sometimes', 'nullable', 'uuid'],
            'first_name' => ['sometimes', 'string', 'max:255'],
            'middle_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'last_name' => ['sometimes', 'string', 'max:255'],
            'preferred_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'gender' => ['sometimes', 'nullable', Rule::enum(MemberGender::class)],
            'date_of_birth' => ['sometimes', 'nullable', 'date', 'before_or_equal:today'],
            'phone' => ['sometimes', 'string', 'max:30'],
            'alternate_phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'national_id_type' => ['sometimes', 'nullable', 'string', 'max:100'],
            'national_id_number' => ['sometimes', 'nullable', 'string', 'max:255'],
            'occupation' => ['sometimes', 'nullable', 'string', 'max:255'],
            'residential_address' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'postal_address' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                foreach ([
                    'organisation_id', 'membership_number', 'created_by_user_id',
                    'status', 'suspended_at', 'suspended_by_user_id',
                    'suspension_reason', 'exited_at', 'exit_reason', 'deceased_at',
                ] as $protected) {
                    if ($this->exists($protected)) {
                        $validator->errors()->add($protected, 'This field cannot be changed directly.');
                    }
                }
            },
        ];
    }
}
