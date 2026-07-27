<?php

namespace App\Domain\Reconciliation\Enums;

enum CashVarianceResolutionType: string
{
    case CollectorRepaid = 'collector_repaid';
    case WrittenOff = 'written_off';
    case SuspenseResolved = 'suspense_resolved';
}
