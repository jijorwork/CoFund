<?php

namespace App\Domain\Wallets\Actions;

use App\Domain\Wallets\Enums\WalletStatus;
use App\Domain\Wallets\Exceptions\InvalidWalletOperation;
use App\Domain\Wallets\Models\Wallet;

class CloseWallet
{
    public function handle(Wallet $wallet): Wallet
    {
        if (! in_array($wallet->status, [WalletStatus::Active, WalletStatus::Suspended], true)) {
            throw new InvalidWalletOperation;
        }

        $wallet->update([
            'status' => WalletStatus::Closed,
            'closed_at' => now(),
            'active_owner_key' => null,
        ]);

        return $wallet->refresh()->load(['branch', 'ledgerAccount', 'walletable']);
    }
}
