<?php

namespace App\Domain\Welfare\Enums;

enum WelfareEnrolmentStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Suspended = 'suspended';
    case Terminated = 'terminated';
    case Expired = 'expired';
}
