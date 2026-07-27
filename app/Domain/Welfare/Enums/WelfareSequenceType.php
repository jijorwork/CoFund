<?php

namespace App\Domain\Welfare\Enums;

enum WelfareSequenceType: string
{
    case Enrolment = 'enrolment';
    case Event = 'event';
    case Claim = 'claim';
    case Liability = 'liability';
    case Payment = 'payment';
    case Amendment = 'amendment';

    public function prefix(): string
    {
        return match ($this) {
            self::Enrolment => 'WEN',
            self::Event => 'WEV',
            self::Claim => 'WCL',
            self::Liability => 'WLB',
            self::Payment => 'WPY',
            self::Amendment => 'WAM',
        };
    }
}
