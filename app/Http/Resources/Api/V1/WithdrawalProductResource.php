<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Withdrawals\Models\WithdrawalProduct;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin WithdrawalProduct */
class WithdrawalProductResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['uuid' => $this->public_id, 'code' => $this->code, 'name' => $this->name, 'product_type' => $this->product_type, 'currency_code' => $this->currency_code, 'eligible_savings_wallet_type' => $this->eligible_savings_wallet_type, 'minimum_withdrawal_minor' => $this->minimum_withdrawal_minor, 'maximum_withdrawal_minor' => $this->maximum_withdrawal_minor, 'minimum_remaining_balance_minor' => $this->minimum_remaining_balance_minor, 'minimum_remaining_balance_basis_points' => $this->minimum_remaining_balance_basis_points, 'maximum_withdrawal_percentage_basis_points' => $this->maximum_withdrawal_percentage_basis_points, 'notice_period' => ['count' => $this->notice_period_count, 'unit' => $this->notice_period_unit], 'restrictions' => ['loan' => $this->loan_restriction_policy, 'guarantor' => $this->guarantor_restriction_policy, 'welfare' => $this->welfare_restriction_policy], 'fee' => ['type' => $this->fee_type, 'value' => $this->fee_value, 'treatment' => $this->fee_treatment], 'allow_partial_payout' => $this->allow_partial_payout, 'require_secondary_approval' => $this->require_secondary_approval, 'secondary_approval_threshold_minor' => $this->secondary_approval_threshold_minor, 'status' => $this->status->value];
    }
}
