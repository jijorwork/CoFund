<?php

namespace App\Domain\Loans\Enums;

enum LoanRepaymentStatus: string
{
    case Posted = 'posted';
    case PartiallyAllocated = 'partially_allocated';
    case Allocated = 'allocated';
    case Reversed = 'reversed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
}
