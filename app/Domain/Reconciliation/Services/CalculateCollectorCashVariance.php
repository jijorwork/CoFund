<?php

namespace App\Domain\Reconciliation\Services;

use App\Domain\Reconciliation\Data\CollectorCashVarianceAmount;

class CalculateCollectorCashVariance
{
    public function calculate(int $expectedMinor, int $declaredMinor): CollectorCashVarianceAmount
    {
        $variance = $declaredMinor - $expectedMinor;

        return new CollectorCashVarianceAmount(
            shortageMinor: $variance < 0 ? abs($variance) : 0,
            overageMinor: $variance > 0 ? $variance : 0,
        );
    }
}
