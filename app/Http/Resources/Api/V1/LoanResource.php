<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Loans\Models\Loan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Loan */
class LoanResource extends JsonResource
{
    /** @return array<string,mixed> */
    public function toArray(Request $request): array
    {
        return ['uuid' => $this->public_id, 'loan_number' => $this->loan_number, 'member_uuid' => $this->member?->public_id, 'product_uuid' => $this->product?->public_id, 'principal_minor' => $this->principal_minor, 'financed_fee_minor' => $this->financed_fee_minor, 'contractual_interest_minor' => $this->contractual_interest_minor, 'total_repayable_minor' => $this->total_repayable_minor, 'currency_code' => $this->currency_code, 'status' => $this->status, 'performance_status' => $this->performance_status, 'outstanding_principal_minor' => $this->outstanding_principal_minor, 'outstanding_interest_minor' => $this->outstanding_interest_minor, 'outstanding_fees_minor' => $this->outstanding_fees_minor, 'outstanding_penalties_minor' => $this->outstanding_penalties_minor, 'arrears_minor' => $this->arrears_minor, 'days_past_due' => $this->days_past_due, 'next_due_date' => $this->next_due_date?->toDateString()];
    }
}
