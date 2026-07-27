<?php

namespace App\Domain\Accounting\Enums;

enum FinancialTransactionStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case Posted = 'posted';
    case Reversed = 'reversed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
}
