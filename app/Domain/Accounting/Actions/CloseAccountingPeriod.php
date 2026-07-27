<?php

namespace App\Domain\Accounting\Actions;

use App\Domain\Accounting\Enums\AccountingPeriodStatus;
use App\Domain\Accounting\Exceptions\InvalidFinancialOperation;
use App\Domain\Accounting\Models\AccountingPeriod;
use App\Models\User;

class CloseAccountingPeriod
{
    public function handle(AccountingPeriod $period, User $actor): AccountingPeriod
    {
        if (! in_array($period->status, [AccountingPeriodStatus::Open, AccountingPeriodStatus::Reopened], true)) {
            throw new InvalidFinancialOperation;
        }

        $period->update([
            'status' => AccountingPeriodStatus::Closed,
            'closed_at' => now(),
            'closed_by_user_id' => $actor->getKey(),
        ]);

        return $period->refresh();
    }
}
