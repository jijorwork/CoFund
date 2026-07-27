<?php

namespace App\Domain\Welfare\Enums;

enum WelfareBenefitPaymentStatus: string
{
    case Posted = 'posted';
    case Reversed = 'reversed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
}
