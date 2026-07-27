<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Reconciliation\Models\CashDepositBatch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CashDepositBatch */
class CashDepositResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->public_id, 'deposit_number' => $this->deposit_number,
            'status' => $this->status->value, 'deposit_method' => $this->deposit_method->value,
            'deposit_date' => $this->deposit_date->toDateString(), 'amount_minor' => $this->amount_minor,
            'currency_code' => $this->currency_code, 'bank_reference' => $this->bank_reference,
            'mobile_money_reference' => $this->mobile_money_reference,
            'source_wallet_uuid' => $this->whenLoaded('sourceWallet', fn () => $this->sourceWallet->public_id),
            'destination_wallet_uuid' => $this->whenLoaded('destinationWallet', fn () => $this->destinationWallet->public_id),
            'financial_transaction_uuid' => $this->whenLoaded('financialTransaction', fn () => $this->financialTransaction?->public_id),
            'confirmed_at' => $this->confirmed_at?->toISOString(), 'notes' => $this->notes,
        ];
    }
}
