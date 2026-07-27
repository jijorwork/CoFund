<?php

namespace App\Domain\Reconciliation\Enums;

enum ReconciliationSequenceType: string
{
    case Session = 'session';
    case Declaration = 'declaration';
    case Handover = 'handover';
    case Deposit = 'deposit';

    public function prefix(): string
    {
        return match ($this) {
            self::Session => 'COL',
            self::Declaration => 'DEC',
            self::Handover => 'HOV',
            self::Deposit => 'DEP',
        };
    }
}
