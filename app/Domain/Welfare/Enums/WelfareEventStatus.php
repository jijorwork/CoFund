<?php

namespace App\Domain\Welfare\Enums;

enum WelfareEventStatus: string
{
    case Reported = 'reported';
    case UnderVerification = 'under_verification';
    case Verified = 'verified';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
}
