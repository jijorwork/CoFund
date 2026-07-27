<?php

namespace App\Domain\Welfare\Services;

use App\Domain\Welfare\Models\WelfareProduct;
use Carbon\CarbonImmutable;

class CalculateWelfareWaitingPeriod
{
    public function endDate(WelfareProduct $product, CarbonImmutable $coverageStart): ?CarbonImmutable
    {
        if ($product->waiting_period_count === 0) {
            return null;
        }

        return match ($product->waiting_period_unit) {
            'days' => $coverageStart->addDays($product->waiting_period_count),
            'weeks' => $coverageStart->addWeeks($product->waiting_period_count),
            'months' => $coverageStart->addMonthsNoOverflow($product->waiting_period_count),
            default => null,
        };
    }
}
