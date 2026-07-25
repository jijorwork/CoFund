<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Contributions\Enums\ContributionFrequency;
use App\Domain\Contributions\Enums\ContributionPlanStatus;
use App\Domain\Contributions\Enums\ContributionPlanType;
use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListContributionPlansRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(RoleAuthorization::class)->can(FoundationPermission::ContributionPlansView->value);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'filter' => ['sometimes', 'array:status,plan_type,frequency,branch_uuid,group_uuid,currency_code'],
            'filter.status' => ['sometimes', Rule::enum(ContributionPlanStatus::class)],
            'filter.plan_type' => ['sometimes', Rule::enum(ContributionPlanType::class)],
            'filter.frequency' => ['sometimes', Rule::enum(ContributionFrequency::class)],
            'filter.branch_uuid' => ['sometimes', 'uuid'],
            'filter.group_uuid' => ['sometimes', 'uuid'],
            'filter.currency_code' => ['sometimes', 'string', 'size:3'],
            'sort' => ['sometimes', Rule::in(['name', '-name', 'code', '-code', 'start_date', '-start_date', 'created_at', '-created_at'])],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
