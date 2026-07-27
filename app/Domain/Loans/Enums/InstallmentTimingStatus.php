<?php

namespace App\Domain\Loans\Enums;

enum InstallmentTimingStatus: string
{
    case Upcoming = 'upcoming';
    case Due = 'due';
    case Overdue = 'overdue';
    case Cancelled = 'cancelled';
}
