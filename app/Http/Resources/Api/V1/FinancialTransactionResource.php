<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Accounting\Enums\EntryType;
use App\Domain\Accounting\Models\FinancialTransaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin FinancialTransaction */
class FinancialTransactionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->public_id,
            'transaction_number' => $this->transaction_number,
            'transaction_type' => $this->transaction_type->value,
            'status' => $this->status->value,
            'currency_code' => $this->currency_code,
            'transaction_date' => $this->transaction_date->toDateString(),
            'description' => $this->description,
            'external_reference' => $this->external_reference,
            'branch' => $this->branch === null ? null : [
                'uuid' => $this->branch->public_id,
                'name' => $this->branch->name,
            ],
            'posted_at' => $this->posted_at?->toISOString(),
            'is_reversal' => $this->original_transaction_id !== null,
            'original_transaction_uuid' => $this->originalTransaction?->public_id,
            'entries' => TransactionEntryResource::collection($this->whenLoaded('entries')),
            'debit_total_minor' => $this->whenLoaded(
                'entries',
                fn (): int => (int) $this->entries->where('entry_type', EntryType::Debit)->sum('amount_minor'),
            ),
            'credit_total_minor' => $this->whenLoaded(
                'entries',
                fn (): int => (int) $this->entries->where('entry_type', EntryType::Credit)->sum('amount_minor'),
            ),
            'reversal' => $this->when(
                $this->relationLoaded('reversalTransaction'),
                fn (): ?array => $this->reversalTransaction === null ? null : [
                    'uuid' => $this->reversalTransaction->public_id,
                    'transaction_number' => $this->reversalTransaction->transaction_number,
                ],
            ),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
