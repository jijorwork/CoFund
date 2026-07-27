<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Accounting\Models\LedgerAccount;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin LedgerAccount */
class LedgerAccountResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->public_id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'category' => $this->category->value,
            'normal_balance' => $this->normal_balance->value,
            'purpose' => $this->purpose->value,
            'currency_code' => $this->currency_code,
            'allows_direct_posting' => $this->allows_direct_posting,
            'is_system_account' => $this->is_system_account,
            'status' => $this->status->value,
            'branch' => $this->branch === null ? null : [
                'uuid' => $this->branch->public_id,
                'name' => $this->branch->name,
            ],
            'parent' => $this->parent === null ? null : [
                'uuid' => $this->parent->public_id,
                'code' => $this->parent->code,
                'name' => $this->parent->name,
            ],
            'activated_at' => $this->activated_at?->toISOString(),
            'closed_at' => $this->closed_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
