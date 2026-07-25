<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Contributions\Enums\ObligationStatus;
use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListContributionScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(RoleAuthorization::class)->can(FoundationPermission::ContributionSchedulesView->value);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'filter' => ['sometimes', 'array:due_from,due_to,status'],
            'filter.due_from' => ['sometimes', 'date'],
            'filter.due_to' => ['sometimes', 'date', 'after_or_equal:filter.due_from'],
            'filter.status' => ['sometimes', Rule::enum(ObligationStatus::class)],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
