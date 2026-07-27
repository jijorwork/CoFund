<?php

namespace App\Domain\Accounting\Actions;

use App\Domain\Accounting\Enums\LedgerAccountStatus;
use App\Domain\Accounting\Exceptions\InvalidFinancialOperation;
use App\Domain\Accounting\Models\LedgerAccount;

class CloseLedgerAccount
{
    public function handle(LedgerAccount $account): LedgerAccount
    {
        if (! in_array($account->status, [LedgerAccountStatus::Active, LedgerAccountStatus::Suspended], true)) {
            throw new InvalidFinancialOperation;
        }

        $account->update(['status' => LedgerAccountStatus::Closed, 'closed_at' => now()]);

        return $account->refresh()->load(['branch', 'parent']);
    }
}
