<?php

namespace App\Domain\Wallets\Services;

use App\Domain\Accounting\Enums\LedgerAccountStatus;
use App\Domain\Accounting\Models\LedgerAccount;
use App\Domain\Organisations\Models\Organisation;
use App\Domain\Wallets\Enums\WalletType;
use App\Domain\Wallets\Exceptions\InvalidWalletOperation;

class SystemWalletAccountResolver
{
    public function resolve(Organisation $organisation, WalletType $type): LedgerAccount
    {
        $code = match ($type) {
            WalletType::MemberSavings => '2000',
            WalletType::MemberLoan => '1800',
            WalletType::OfficeCash => '1100',
            WalletType::CollectorCash => '1200',
            WalletType::Bank => '1300',
            WalletType::MobileMoney => '1400',
            WalletType::Suspense => '1500',
            WalletType::PettyCash => '1600',
        };

        return LedgerAccount::query()
            ->whereBelongsTo($organisation)
            ->where('code', $code)
            ->where('status', LedgerAccountStatus::Active)
            ->where('allows_direct_posting', true)
            ->first() ?? throw new InvalidWalletOperation('The required system ledger account is unavailable.');
    }
}
