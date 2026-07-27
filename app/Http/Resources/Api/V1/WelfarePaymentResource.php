<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Welfare\Models\WelfareBenefitPayment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin WelfareBenefitPayment */
class WelfarePaymentResource extends JsonResource
{
    /** @return array<string,mixed> */
    public function toArray(Request $request): array
    {
        return ['uuid' => $this->public_id, 'payment_number' => $this->payment_number, 'claim' => ['uuid' => $this->claim->public_id, 'claim_number' => $this->claim->claim_number], 'recipient' => ['type' => $this->recipient_type, 'name' => $this->recipient_name, 'beneficiary_uuid' => $this->beneficiary?->public_id], 'amount_minor' => $this->amount_minor, 'currency_code' => $this->currency_code, 'payment_method' => $this->payment_method, 'payment_date' => $this->payment_date->toDateString(), 'source_wallet' => ['uuid' => $this->sourceWallet->public_id, 'name' => $this->sourceWallet->name], 'status' => $this->status->value, 'receipt' => $this->receipt === null ? null : ['uuid' => $this->receipt->public_id, 'receipt_number' => $this->receipt->receipt_number], 'transaction' => ['uuid' => $this->financialTransaction->public_id, 'transaction_number' => $this->financialTransaction->transaction_number]];
    }
}
