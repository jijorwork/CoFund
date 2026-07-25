<?php

namespace App\Domain\Contributions\Enums;

enum ContributionPositionStatus: string
{
    case Current = 'current';
    case InArrears = 'in_arrears';
    case Prepaid = 'prepaid';
    case NoSchedule = 'no_schedule';
    case Suspended = 'suspended';
    case Ended = 'ended';
}
