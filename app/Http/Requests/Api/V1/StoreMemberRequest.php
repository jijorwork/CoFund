<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Membership\Enums\MemberContactType;
use App\Domain\Membership\Enums\MemberGender;
use App\Domain\Membership\Enums\MemberStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(RoleAuthorization::class)->can(FoundationPermission::MembersCreate->value);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'branch_uuid' => ['nullable', 'uuid'],
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'preferred_name' => ['nullable', 'string', 'max:255'],
            'gender' => ['nullable', Rule::enum(MemberGender::class)],
            'date_of_birth' => ['nullable', 'date', 'before_or_equal:today'],
            'phone' => ['required', 'string', 'max:30'],
            'alternate_phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'national_id_type' => ['nullable', 'string', 'max:100'],
            'national_id_number' => ['nullable', 'string', 'max:255'],
            'occupation' => ['nullable', 'string', 'max:255'],
            'residential_address' => ['nullable', 'string', 'max:2000'],
            'postal_address' => ['nullable', 'string', 'max:2000'],
            'joined_at' => ['nullable', 'date'],
            'status' => ['sometimes', Rule::in([MemberStatus::Pending->value, MemberStatus::Active->value])],
            'notes' => ['nullable', 'string', 'max:5000'],
            'contacts' => ['sometimes', 'array', 'max:10'],
            'contacts.*.contact_type' => ['required', Rule::enum(MemberContactType::class)],
            'contacts.*.full_name' => ['required', 'string', 'max:255'],
            'contacts.*.relationship' => ['required', 'string', 'max:100'],
            'contacts.*.phone' => ['required', 'string', 'max:30'],
            'contacts.*.alternate_phone' => ['nullable', 'string', 'max:30'],
            'contacts.*.email' => ['nullable', 'email', 'max:255'],
            'contacts.*.percentage_entitlement' => ['nullable', 'decimal:0,2', 'between:0,100'],
            'contacts.*.is_primary' => ['sometimes', 'boolean'],
            'contacts.*.is_emergency_contact' => ['sometimes', 'boolean'],
        ];
    }
}
