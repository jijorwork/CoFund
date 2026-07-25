<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use Illuminate\Foundation\Http\FormRequest;

class StoreContributionEnrolmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(RoleAuthorization::class)->can(FoundationPermission::ContributionEnrolmentsCreate->value);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'member_uuid' => ['required', 'uuid'],
            'plan_uuid' => ['required', 'uuid'],
            'selected_amount_minor' => ['nullable', 'integer', 'min:1'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ];
    }
}
