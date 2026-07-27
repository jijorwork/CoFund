<?php

namespace App\Domain\Reconciliation\Enums;

enum CashVarianceType: string
{
    case Shortage = 'shortage';
    case Overage = 'overage';
}
