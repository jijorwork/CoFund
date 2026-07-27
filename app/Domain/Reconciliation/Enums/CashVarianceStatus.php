<?php

namespace App\Domain\Reconciliation\Enums;

enum CashVarianceStatus: string
{
    case Open = 'open';
    case UnderReview = 'under_review';
    case Resolved = 'resolved';
    case WrittenOff = 'written_off';
    case Reversed = 'reversed';
}
