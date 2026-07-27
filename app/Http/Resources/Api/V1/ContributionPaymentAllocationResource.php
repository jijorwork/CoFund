<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Collections\Models\ContributionPaymentAllocation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ContributionPaymentAllocation */
class ContributionPaymentAllocationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->public_id,
            'obligation_uuid' => $this->obligation->public_id,
            'enrolment_uuid' => $this->obligation->enrolment->public_id,
            'due_date' => $this->obligation->due_date->toDateString(),
            'amount_minor' => $this->amount_minor,
            'currency_code' => $this->currency_code,
            'status' => $this->status->value,
            'obligation_satisfaction_status' => $this->obligation->satisfaction_status->value,
            'allocated_at' => $this->allocated_at->toIso8601String(),
        ];
    }
}
