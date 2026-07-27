<?php

namespace App\Domain\Accounting\Actions;

use App\Domain\Accounting\Enums\LedgerAccountStatus;
use App\Domain\Accounting\Exceptions\InvalidFinancialOperation;
use App\Domain\Accounting\Models\LedgerAccount;

class ActivateLedgerAccount
{
    public function handle(LedgerAccount $account): LedgerAccount
    {
        if (! in_array($account->status, [LedgerAccountStatus::Draft, LedgerAccountStatus::Suspended], true)) {
            throw new InvalidFinancialOperation;
        }

        if ($account->normal_balance !== $account->category->normalBalance()) {
            throw new InvalidFinancialOperation;
        }

        $account->update([
            'status' => LedgerAccountStatus::Active,
            'activated_at' => $account->activated_at ?? now(),
            'closed_at' => null,
        ]);

        return $account->refresh()->load(['branch', 'parent']);
    }
}
