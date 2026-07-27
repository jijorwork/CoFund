<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Reconciliation\Models\CollectorCashHandover;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CollectorCashHandover */
class CashHandoverResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->public_id, 'handover_number' => $this->handover_number,
            'status' => $this->status->value, 'declared_cash_minor' => $this->declared_cash_minor,
            'accepted_cash_minor' => $this->accepted_cash_minor, 'shortage_minor' => $this->shortage_minor,
            'overage_minor' => $this->overage_minor, 'currency_code' => $this->currency_code,
            'session_uuid' => $this->whenLoaded('session', fn () => $this->session->public_id),
            'collector_wallet_uuid' => $this->whenLoaded('collectorWallet', fn () => $this->collectorWallet->public_id),
            'destination_wallet_uuid' => $this->whenLoaded('destinationWallet', fn () => $this->destinationWallet->public_id),
            'financial_transaction_uuid' => $this->whenLoaded('financialTransaction', fn () => $this->financialTransaction?->public_id),
            'handed_over_at' => $this->handed_over_at?->toISOString(), 'received_at' => $this->received_at?->toISOString(),
            'approved_at' => $this->approved_at?->toISOString(), 'rejection_reason' => $this->rejection_reason,
        ];
    }
}
