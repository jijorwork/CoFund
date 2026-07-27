<?php

namespace App\Domain\Wallets\Actions;

use App\Domain\Wallets\Enums\WalletStatus;
use App\Domain\Wallets\Exceptions\InvalidWalletOperation;
use App\Domain\Wallets\Models\Wallet;

class ReinstateWallet
{
    public function handle(Wallet $wallet): Wallet
    {
        if ($wallet->status !== WalletStatus::Suspended) {
            throw new InvalidWalletOperation;
        }

        $wallet->update(['status' => WalletStatus::Active]);

        return $wallet->refresh()->load(['branch', 'ledgerAccount', 'walletable']);
    }
}
