<?php

namespace App\Domain\Contributions\Enums;

enum ContributionFrequency: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Flexible = 'flexible';
    case OneTime = 'one_time';
}
