<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Expenses\Models\ExpensePayment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ExpensePayment */
class ExpensePaymentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['uuid' => $this->public_id, 'payment_number' => $this->payment_number, 'expense_uuid' => $this->whenLoaded('expenseRequest', fn () => $this->expenseRequest->public_id), 'payment_type' => $this->payment_type->value, 'payment_method' => $this->payment_method->value, 'amount_minor' => $this->amount_minor, 'currency_code' => $this->currency_code, 'source_wallet_uuid' => $this->whenLoaded('sourceWallet', fn () => $this->sourceWallet->public_id), 'payment_date' => $this->payment_date->toDateString(), 'payee_name' => $this->payee_name, 'status' => $this->status->value, 'financial_transaction_uuid' => $this->whenLoaded('financialTransaction', fn () => $this->financialTransaction->public_id)];
    }
}
