<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Withdrawals\Models\WithdrawalPayout;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin WithdrawalPayout */
class WithdrawalPayoutResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['uuid' => $this->public_id, 'payout_number' => $this->payout_number, 'request' => ['uuid' => $this->request->public_id, 'request_number' => $this->request->request_number], 'gross_withdrawal_minor' => $this->gross_withdrawal_minor, 'fee_minor' => $this->fee_minor, 'net_payout_minor' => $this->net_payout_minor, 'currency_code' => $this->currency_code, 'payment_method' => $this->payment_method, 'payout_date' => $this->payout_date->toDateString(), 'recipient' => ['type' => $this->recipient_type, 'name' => $this->recipient_name], 'destination_wallet' => ['uuid' => $this->destinationWallet->public_id, 'name' => $this->destinationWallet->name], 'status' => $this->status->value, 'receipt' => $this->receipt === null ? null : ['uuid' => $this->receipt->public_id, 'receipt_number' => $this->receipt->receipt_number], 'transaction' => ['uuid' => $this->financialTransaction->public_id, 'transaction_number' => $this->financialTransaction->transaction_number]];
    }
}
