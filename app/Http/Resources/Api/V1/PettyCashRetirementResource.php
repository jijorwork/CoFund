<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Expenses\Models\PettyCashRetirement;
use App\Domain\Expenses\Models\PettyCashRetirementItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PettyCashRetirement */
class PettyCashRetirementResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['uuid' => $this->public_id, 'retirement_number' => $this->retirement_number, 'submitted_amount_minor' => $this->submitted_amount_minor, 'accepted_expense_minor' => $this->accepted_expense_minor, 'returned_cash_minor' => $this->returned_cash_minor, 'shortage_minor' => $this->shortage_minor, 'currency_code' => $this->currency_code, 'status' => $this->status->value, 'items' => $this->whenLoaded('items', fn () => $this->items->map(fn (PettyCashRetirementItem $item): array => ['uuid' => $item->public_id, 'description' => $item->description, 'amount_minor' => $item->amount_minor, 'expense_date' => $item->expense_date->toDateString()]))];
    }
}
