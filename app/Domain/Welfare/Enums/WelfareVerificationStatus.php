<?php

namespace App\Domain\Welfare\Enums;

enum WelfareVerificationStatus: string
{
    case NotRequired = 'not_required';
    case Pending = 'pending';
    case Verified = 'verified';
    case Failed = 'failed';
}
