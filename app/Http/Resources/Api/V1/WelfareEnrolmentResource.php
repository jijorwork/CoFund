<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Welfare\Models\WelfareEnrolment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin WelfareEnrolment */
class WelfareEnrolmentResource extends JsonResource
{
    /** @return array<string,mixed> */
    public function toArray(Request $request): array
    {
        return ['uuid' => $this->public_id, 'enrolment_number' => $this->enrolment_number, 'member' => ['uuid' => $this->member->public_id, 'membership_number' => $this->member->membership_number], 'product' => ['uuid' => $this->product->public_id, 'code' => $this->product->code, 'name' => $this->product->name], 'coverage_start_date' => $this->coverage_start_date->toDateString(), 'waiting_period_end_date' => $this->waiting_period_end_date?->toDateString(), 'status' => $this->status->value];
    }
}
