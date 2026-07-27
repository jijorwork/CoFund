<?php

namespace App\Domain\Loans\Enums;

enum InterestMethod: string
{
    case Flat = 'flat';
    case ReducingBalance = 'reducing_balance';
    case ZeroInterest = 'zero_interest';
}
