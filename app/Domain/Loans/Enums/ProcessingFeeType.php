<?php

namespace App\Domain\Loans\Enums;

enum ProcessingFeeType: string
{
    case None = 'none';
    case Fixed = 'fixed';
    case Percentage = 'percentage';
}
