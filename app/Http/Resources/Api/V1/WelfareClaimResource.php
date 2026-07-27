<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Welfare\Models\WelfareClaim;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin WelfareClaim */
class WelfareClaimResource extends JsonResource
{
    /** @return array<string,mixed> */
    public function toArray(Request $request): array
    {
        return ['uuid' => $this->public_id, 'claim_number' => $this->claim_number, 'member' => ['uuid' => $this->member->public_id, 'membership_number' => $this->member->membership_number], 'product' => ['uuid' => $this->product->public_id, 'code' => $this->product->code, 'name' => $this->product->name], 'event' => ['uuid' => $this->event->public_id, 'event_number' => $this->event->event_number, 'event_date' => $this->event->event_date->toDateString()], 'requested_benefit_minor' => $this->requested_benefit_minor, 'eligible_benefit_minor' => $this->eligible_benefit_minor, 'approved_benefit_minor' => $this->approved_benefit_minor, 'total_paid_minor' => $this->total_paid_minor, 'outstanding_payable_minor' => $this->outstanding_payable_minor, 'beneficiary_count' => $this->whenCounted('beneficiaries'), 'eligibility_status' => $this->eligibility_status->value, 'verification_status' => $this->verification_status->value, 'approval_status' => $this->approval_status->value, 'payment_status' => $this->payment_status->value, 'status' => $this->status->value, 'claim_date' => $this->claim_date->toDateString(), 'submitted_at' => $this->submitted_at?->toIso8601String(), 'approved_at' => $this->approved_at?->toIso8601String(), 'settled_at' => $this->settled_at?->toIso8601String()];
    }
}
