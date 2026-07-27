<?php

namespace App\Domain\Withdrawals\Enums;

enum WithdrawalPayoutStatus: string
{
    case Posted = 'posted';
    case Reversed = 'reversed';
}
