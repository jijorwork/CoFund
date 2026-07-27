<?php

namespace App\Domain\Withdrawals\Enums;

enum WithdrawalSequenceType: string
{
    case Product = 'product';
    case Hold = 'hold';
    case Request = 'request';
    case Payout = 'payout';
    case Amendment = 'amendment';

    public function prefix(): string
    {
        return match ($this) {
            self::Hold => 'SHD',
            self::Request => 'WDR',
            self::Payout => 'WDP',
            self::Amendment => 'WAM',
            self::Product => 'WPR',
        };
    }
}
