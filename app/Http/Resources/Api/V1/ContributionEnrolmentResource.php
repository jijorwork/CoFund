<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Contributions\Models\MemberPlanEnrolment;
use App\Domain\Contributions\Services\CalculateContributionPosition;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin MemberPlanEnrolment */
class ContributionEnrolmentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->public_id,
            'member' => [
                'uuid' => $this->member->public_id,
                'membership_number' => $this->member->membership_number,
                'full_name' => $this->member->fullName(),
            ],
            'plan' => [
                'uuid' => $this->contributionPlan->public_id,
                'name' => $this->contributionPlan->name,
                'code' => $this->contributionPlan->code,
            ],
            'selected_amount_minor' => $this->selected_amount_minor,
            'currency_code' => $this->currency_code,
            'start_date' => $this->start_date->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'next_due_date' => $this->next_due_date?->toDateString(),
            'status' => $this->status->value,
            'approval_status' => $this->approval_status->value,
            'position' => app(CalculateContributionPosition::class)->calculate($this->resource)->toArray(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
