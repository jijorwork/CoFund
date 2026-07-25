<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewMemberDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(RoleAuthorization::class)->can(FoundationPermission::MembersVerifyDocuments->value);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'reason' => [
                Rule::requiredIf(fn (): bool => $this->route()->getActionMethod() === 'reject'),
                'nullable', 'string', 'max:2000',
            ],
        ];
    }
}
