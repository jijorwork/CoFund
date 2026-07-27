<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Membership\Models\Member;
use App\Domain\Organisations\Models\Branch;
use App\Domain\Organisations\Models\Organisation;
use App\Domain\Wallets\Models\Wallet;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Wallet */
class WalletResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->public_id,
            'wallet_type' => $this->wallet_type->value,
            'name' => $this->name,
            'code' => $this->code,
            'currency_code' => $this->currency_code,
            'status' => $this->status->value,
            'is_system_wallet' => $this->is_system_wallet,
            'owner' => $this->owner(),
            'branch' => $this->branch === null ? null : [
                'uuid' => $this->branch->public_id,
                'name' => $this->branch->name,
            ],
            'ledger_account' => [
                'uuid' => $this->ledgerAccount->public_id,
                'code' => $this->ledgerAccount->code,
                'name' => $this->ledgerAccount->name,
                'normal_balance' => $this->ledgerAccount->normal_balance->value,
            ],
            'opened_at' => $this->opened_at->toISOString(),
            'closed_at' => $this->closed_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }

    /** @return array<string, string>|null */
    private function owner(): ?array
    {
        return match (true) {
            $this->walletable instanceof Member => [
                'type' => 'member',
                'uuid' => $this->walletable->public_id,
                'name' => $this->walletable->fullName(),
            ],
            $this->walletable instanceof Branch => [
                'type' => 'branch',
                'uuid' => $this->walletable->public_id,
                'name' => $this->walletable->name,
            ],
            $this->walletable instanceof Organisation => [
                'type' => 'organisation',
                'uuid' => $this->walletable->public_id,
                'name' => $this->walletable->name,
            ],
            $this->walletable instanceof User => [
                'type' => 'collector_user',
                'uuid' => $this->walletable->public_id,
                'name' => $this->walletable->name,
            ],
            default => null,
        };
    }
}
