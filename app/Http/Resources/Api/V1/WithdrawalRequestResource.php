<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Withdrawals\Models\WithdrawalRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin WithdrawalRequest */
class WithdrawalRequestResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['uuid' => $this->public_id, 'request_number' => $this->request_number, 'member' => ['uuid' => $this->member->public_id, 'membership_number' => $this->member->membership_number], 'product' => ['uuid' => $this->product->public_id, 'code' => $this->product->code, 'name' => $this->product->name], 'reason' => $this->reason === null ? null : ['uuid' => $this->reason->public_id, 'code' => $this->reason->code, 'name' => $this->reason->name], 'savings_wallet' => ['uuid' => $this->savingsWallet->public_id, 'name' => $this->savingsWallet->name], 'requested_amount_minor' => $this->requested_amount_minor, 'eligible_amount_minor' => $this->eligible_amount_minor, 'approved_amount_minor' => $this->approved_amount_minor, 'fee_minor' => $this->fee_minor, 'net_payout_minor' => $this->net_payout_minor, 'total_paid_minor' => $this->total_paid_minor, 'outstanding_approved_minor' => $this->outstanding_approved_minor, 'eligibility_status' => $this->eligibility_status, 'approval_status' => $this->approval_status, 'payment_status' => $this->payment_status, 'status' => $this->status->value, 'request_date' => $this->request_date->toDateString()];
    }
}
