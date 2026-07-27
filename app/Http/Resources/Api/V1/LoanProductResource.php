<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Loans\Models\LoanProduct;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin LoanProduct */
class LoanProductResource extends JsonResource
{
    /** @return array<string,mixed> */
    public function toArray(Request $request): array
    {
        return ['uuid' => $this->public_id, 'code' => $this->code, 'name' => $this->name, 'description' => $this->description, 'branch_uuid' => $this->branch?->public_id, 'currency_code' => $this->currency_code, 'minimum_amount_minor' => $this->minimum_amount_minor, 'maximum_amount_minor' => $this->maximum_amount_minor, 'minimum_term' => $this->minimum_term, 'maximum_term' => $this->maximum_term, 'term_unit' => $this->term_unit, 'repayment_frequency' => $this->repayment_frequency, 'interest_method' => $this->interest_method, 'interest_rate_basis_points' => $this->interest_rate_basis_points, 'grace_period_days' => $this->grace_period_days, 'required_guarantor_count' => $this->required_guarantor_count, 'requires_collateral' => $this->requires_collateral, 'processing_fee_type' => $this->processing_fee_type, 'processing_fee_treatment' => $this->processing_fee_treatment, 'status' => $this->status];
    }
}
