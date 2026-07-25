<?php

namespace App\Domain\Contributions\Enums;

enum EnrolmentStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Suspended = 'suspended';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Ended = 'ended';
}
