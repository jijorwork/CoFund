<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Collections\Models\Receipt;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Receipt */
class ReceiptResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->public_id,
            'receipt_number' => $this->receipt_number,
            'receipt_type' => $this->receipt_type->value,
            'status' => $this->status->value,
            'issued_at' => $this->issued_at->toIso8601String(),
            'member' => ['uuid' => $this->member->public_id, 'membership_number' => $this->member->membership_number],
            'payment' => $this->payment === null ? null : ['uuid' => $this->payment->public_id, 'payment_number' => $this->payment->payment_number],
            'loan_repayment' => $this->loanRepayment === null ? null : ['uuid' => $this->loanRepayment->public_id, 'repayment_number' => $this->loanRepayment->repayment_number],
            'welfare_benefit_payment' => $this->welfareBenefitPayment === null ? null : ['uuid' => $this->welfareBenefitPayment->public_id, 'payment_number' => $this->welfareBenefitPayment->payment_number],
            'transaction' => [
                'uuid' => $this->financialTransaction->public_id,
                'transaction_number' => $this->financialTransaction->transaction_number,
            ],
            'payment_method' => $this->payment_method,
            'amount_minor' => $this->amount_minor,
            'currency_code' => $this->currency_code,
            'verification_code' => $this->verification_code,
        ];
    }
}
