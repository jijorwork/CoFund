<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Accounting\Models\TransactionEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TransactionEntry */
class TransactionEntryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->public_id,
            'sequence_number' => $this->sequence_number,
            'account' => [
                'uuid' => $this->ledgerAccount->public_id,
                'code' => $this->ledgerAccount->code,
                'name' => $this->ledgerAccount->name,
            ],
            'entry_type' => $this->entry_type->value,
            'amount_minor' => $this->amount_minor,
            'amount' => $this->money($this->amount_minor),
            'currency_code' => $this->currency_code,
            'wallet' => $this->wallet === null ? null : [
                'uuid' => $this->wallet->public_id,
                'wallet_type' => $this->wallet->wallet_type->value,
                'name' => $this->wallet->name,
            ],
            'member' => $this->member === null ? null : [
                'uuid' => $this->member->public_id,
                'membership_number' => $this->member->membership_number,
                'name' => $this->member->fullName(),
            ],
            'description' => $this->description,
        ];
    }

    private function money(int $minor): string
    {
        return intdiv($minor, 100).'.'.str_pad((string) ($minor % 100), 2, '0', STR_PAD_LEFT);
    }
}
