<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Welfare\Models\WelfareProduct;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin WelfareProduct */
class WelfareProductResource extends JsonResource
{
    /** @return array<string,mixed> */
    public function toArray(Request $request): array
    {
        return ['uuid' => $this->public_id, 'code' => $this->code, 'name' => $this->name, 'product_type' => $this->product_type, 'currency_code' => $this->currency_code, 'eligibility' => ['basis' => $this->eligibility_basis, 'minimum_membership_days' => $this->minimum_membership_days, 'minimum_contribution_count' => $this->minimum_contribution_count, 'minimum_contribution_amount_minor' => $this->minimum_contribution_amount_minor], 'waiting_period' => ['count' => $this->waiting_period_count, 'unit' => $this->waiting_period_unit], 'benefit' => ['calculation_type' => $this->benefit_calculation_type, 'fixed_minor' => $this->fixed_benefit_minor, 'rate_basis_points' => $this->benefit_percentage_basis_points, 'base' => $this->benefit_percentage_base, 'minimum_minor' => $this->minimum_benefit_minor, 'maximum_minor' => $this->maximum_benefit_minor], 'required_documents' => $this->required_document_types ?? [], 'secondary_approval_threshold_minor' => $this->secondary_approval_threshold_minor, 'partial_payment_allowed' => $this->partial_payment_allowed, 'status' => $this->status->value];
    }
}
