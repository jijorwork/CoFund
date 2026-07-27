<?php

namespace App\Domain\Accounting\Enums;

enum AccountingPeriodStatus: string
{
    case Open = 'open';
    case Closing = 'closing';
    case Closed = 'closed';
    case Reopened = 'reopened';

    public function allowsPosting(): bool
    {
        return in_array($this, [self::Open, self::Reopened], true);
    }
}
