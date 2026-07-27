<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use Illuminate\Foundation\Http\FormRequest;

class TrialBalanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(RoleAuthorization::class)->can(FoundationPermission::TrialBalanceView->value);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'as_of' => ['sometimes', 'nullable', 'date'],
            'branch_uuid' => ['sometimes', 'nullable', 'uuid'],
        ];
    }
}
