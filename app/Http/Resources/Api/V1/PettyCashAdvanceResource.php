<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Expenses\Models\PettyCashAdvance;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PettyCashAdvance */
class PettyCashAdvanceResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['uuid' => $this->public_id, 'advance_number' => $this->advance_number, 'purpose' => $this->purpose, 'requested_amount_minor' => $this->requested_amount_minor, 'approved_amount_minor' => $this->approved_amount_minor, 'disbursed_amount_minor' => $this->disbursed_amount_minor, 'retired_amount_minor' => $this->retired_amount_minor, 'outstanding_amount_minor' => max(0, $this->disbursed_amount_minor - $this->retired_amount_minor), 'shortage_minor' => $this->shortage_minor, 'currency_code' => $this->currency_code, 'status' => $this->status->value, 'retirement_due_date' => $this->retirement_due_date?->toDateString()];
    }
}
