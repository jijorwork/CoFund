<?php

namespace App\Domain\Loans\Enums;

enum RepaymentFrequency: string
{
    case Weekly = 'weekly';
    case Fortnightly = 'fortnightly';
    case Monthly = 'monthly';
}
