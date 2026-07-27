<?php

namespace App\Domain\Accounting\Services;

use App\Domain\Accounting\Exceptions\InvalidFinancialOperation;
use App\Domain\Accounting\Models\AccountingPeriod;
use App\Domain\Organisations\Models\Organisation;
use Carbon\CarbonImmutable;

class FindAccountingPeriodForDate
{
    public function assertPostingAllowed(Organisation $organisation, CarbonImmutable $date): ?AccountingPeriod
    {
        if (! AccountingPeriod::query()->whereBelongsTo($organisation)->exists()) {
            return null;
        }

        $period = AccountingPeriod::query()
            ->whereBelongsTo($organisation)
            ->whereDate('starts_on', '<=', $date->toDateString())
            ->whereDate('ends_on', '>=', $date->toDateString())
            ->first();

        if ($period === null || ! $period->status->allowsPosting()) {
            throw new InvalidFinancialOperation('The transaction date is not in an open accounting period.');
        }

        return $period;
    }
}
