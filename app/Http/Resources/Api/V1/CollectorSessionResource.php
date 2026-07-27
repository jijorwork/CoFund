<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Reconciliation\Models\CollectorCollectionSession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CollectorCollectionSession */
class CollectorSessionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->public_id,
            'session_number' => $this->session_number,
            'session_date' => $this->session_date->toDateString(),
            'status' => $this->status->value,
            'collector_uuid' => $this->whenLoaded('collector', fn () => $this->collector->public_id),
            'collector_wallet_uuid' => $this->whenLoaded('collectorWallet', fn () => $this->collectorWallet->public_id),
            'expected_cash_minor' => $this->expected_cash_minor,
            'expected_non_cash_minor' => $this->expected_non_cash_minor,
            'declared_cash_minor' => $this->declared_cash_minor,
            'shortage_minor' => $this->shortage_minor,
            'overage_minor' => $this->overage_minor,
            'currency_code' => $this->currency_code,
            'opened_at' => $this->opened_at->toISOString(),
            'closed_at' => $this->closed_at?->toISOString(),
            'submitted_at' => $this->submitted_at?->toISOString(),
            'approved_at' => $this->approved_at?->toISOString(),
            'rejection_reason' => $this->rejection_reason,
            'notes' => $this->notes,
        ];
    }
}
