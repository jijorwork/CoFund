<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Membership\Enums\MemberDocumentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadMemberDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(RoleAuthorization::class)->can(FoundationPermission::MembersUploadDocuments->value);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'document_type' => ['required', Rule::enum(MemberDocumentType::class)],
            'file' => [
                'required', 'file', 'max:10240',
                'mimetypes:application/pdf,image/jpeg,image/png,image/webp',
                'mimes:pdf,jpg,jpeg,png,webp',
            ],
        ];
    }
}
