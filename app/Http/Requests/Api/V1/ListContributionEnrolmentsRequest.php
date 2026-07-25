<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Contributions\Enums\ApprovalStatus;
use App\Domain\Contributions\Enums\EnrolmentStatus;
use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListContributionEnrolmentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(RoleAuthorization::class)->can(FoundationPermission::ContributionEnrolmentsView->value);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'filter' => ['sometimes', 'array:member_uuid,plan_uuid,branch_uuid,group_uuid,status,approval_status,start_date_from,start_date_to'],
            'filter.member_uuid' => ['sometimes', 'uuid'],
            'filter.plan_uuid' => ['sometimes', 'uuid'],
            'filter.branch_uuid' => ['sometimes', 'uuid'],
            'filter.group_uuid' => ['sometimes', 'uuid'],
            'filter.status' => ['sometimes', Rule::enum(EnrolmentStatus::class)],
            'filter.approval_status' => ['sometimes', Rule::enum(ApprovalStatus::class)],
            'filter.start_date_from' => ['sometimes', 'date'],
            'filter.start_date_to' => ['sometimes', 'date', 'after_or_equal:filter.start_date_from'],
            'sort' => ['sometimes', Rule::in(['start_date', '-start_date', 'next_due_date', '-next_due_date', 'created_at', '-created_at'])],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
