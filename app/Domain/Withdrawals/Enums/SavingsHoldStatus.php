<?php

namespace App\Domain\Withdrawals\Enums;

enum SavingsHoldStatus: string
{
    case Active = 'active';
    case Released = 'released';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
}
