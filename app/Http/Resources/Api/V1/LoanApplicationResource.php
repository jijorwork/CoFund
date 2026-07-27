<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Loans\Models\LoanApplication;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin LoanApplication */
class LoanApplicationResource extends JsonResource
{
    /** @return array<string,mixed> */
    public function toArray(Request $request): array
    {
        return ['uuid' => $this->public_id, 'application_number' => $this->application_number, 'member_uuid' => $this->member?->public_id, 'product_uuid' => $this->product?->public_id, 'branch_uuid' => $this->branch?->public_id, 'requested_principal_minor' => $this->requested_principal_minor, 'requested_term' => $this->requested_term, 'repayment_frequency' => $this->repayment_frequency, 'purpose' => $this->purpose, 'eligibility_status' => $this->eligibility_status, 'eligibility' => $this->eligibility_snapshot, 'status' => $this->workflow_status, 'submitted_at' => $this->submitted_at?->toISOString(), 'approved_at' => $this->approved_at?->toISOString()];
    }
}
