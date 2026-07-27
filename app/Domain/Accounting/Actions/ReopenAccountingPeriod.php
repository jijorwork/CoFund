<?php

namespace App\Domain\Accounting\Actions;

use App\Domain\Accounting\Enums\AccountingPeriodStatus;
use App\Domain\Accounting\Exceptions\InvalidFinancialOperation;
use App\Domain\Accounting\Models\AccountingPeriod;
use App\Models\User;

class ReopenAccountingPeriod
{
    public function handle(AccountingPeriod $period, User $actor, string $reason): AccountingPeriod
    {
        if ($period->status !== AccountingPeriodStatus::Closed || trim($reason) === '') {
            throw new InvalidFinancialOperation;
        }

        $period->update([
            'status' => AccountingPeriodStatus::Reopened,
            'reopened_at' => now(),
            'reopened_by_user_id' => $actor->getKey(),
            'reopening_reason' => $reason,
        ]);

        return $period->refresh();
    }
}
