<?php

namespace App\Domain\Expenses\Enums;

enum ExpensePaymentStatus: string
{
    case Pending = 'pending';
    case Posted = 'posted';
    case Reversed = 'reversed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
}
