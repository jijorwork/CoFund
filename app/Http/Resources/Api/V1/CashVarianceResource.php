<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Reconciliation\Models\CollectorCashVariance;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CollectorCashVariance */
class CashVarianceResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->public_id, 'variance_type' => $this->variance_type->value,
            'amount_minor' => $this->amount_minor, 'currency_code' => $this->currency_code,
            'status' => $this->status->value, 'reason' => $this->reason,
            'resolution_type' => $this->resolution_type?->value, 'resolution_notes' => $this->resolution_notes,
            'reported_at' => $this->reported_at->toISOString(), 'resolved_at' => $this->resolved_at?->toISOString(),
        ];
    }
}
