<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Expenses\Models\ExpenseRequestItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ExpenseRequestItem */
class ExpenseRequestItemResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['uuid' => $this->public_id, 'description' => $this->description, 'quantity' => $this->quantity, 'unit_amount_minor' => $this->unit_amount_minor, 'line_total_minor' => $this->line_total_minor, 'currency_code' => $this->currency_code, 'sequence_number' => $this->sequence_number, 'category_uuid' => $this->whenLoaded('category', fn () => $this->category?->public_id), 'ledger_account_uuid' => $this->whenLoaded('ledgerAccount', fn () => $this->ledgerAccount?->public_id)];
    }
}
