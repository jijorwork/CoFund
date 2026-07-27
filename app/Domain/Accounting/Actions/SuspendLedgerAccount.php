<?php

namespace App\Domain\Accounting\Actions;

use App\Domain\Accounting\Enums\LedgerAccountStatus;
use App\Domain\Accounting\Exceptions\InvalidFinancialOperation;
use App\Domain\Accounting\Models\LedgerAccount;

class SuspendLedgerAccount
{
    public function handle(LedgerAccount $account): LedgerAccount
    {
        if ($account->status !== LedgerAccountStatus::Active) {
            throw new InvalidFinancialOperation;
        }

        $account->update(['status' => LedgerAccountStatus::Suspended]);

        return $account->refresh()->load(['branch', 'parent']);
    }
}
