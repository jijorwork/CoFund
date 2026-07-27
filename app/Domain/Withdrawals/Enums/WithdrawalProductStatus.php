<?php

namespace App\Domain\Withdrawals\Enums;

enum WithdrawalProductStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Suspended = 'suspended';
    case Closed = 'closed';
    case Archived = 'archived';
}
