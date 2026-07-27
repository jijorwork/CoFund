<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Expenses\Models\ExpenseCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ExpenseCategory */
class ExpenseCategoryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['uuid' => $this->public_id, 'code' => $this->code, 'name' => $this->name, 'description' => $this->description, 'parent_uuid' => $this->whenLoaded('parent', fn () => $this->parent?->public_id), 'default_ledger_account_uuid' => $this->whenLoaded('defaultLedgerAccount', fn () => $this->defaultLedgerAccount->public_id), 'requires_receipt' => $this->requires_receipt, 'requires_preapproval' => $this->requires_preapproval, 'requires_secondary_approval' => $this->requires_secondary_approval, 'maximum_without_secondary_approval_minor' => $this->maximum_without_secondary_approval_minor, 'allowed_payment_methods' => $this->allowed_payment_methods, 'status' => $this->status->value, 'created_at' => $this->created_at?->toISOString()];
    }
}
