<?php

namespace App\Domain\Welfare\Enums;

enum WelfareLiabilityStatus: string
{
    case Recognised = 'recognised';
    case Reversed = 'reversed';
    case Failed = 'failed';
}
