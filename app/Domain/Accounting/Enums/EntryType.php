<?php

namespace App\Domain\Accounting\Enums;

enum EntryType: string
{
    case Debit = 'debit';
    case Credit = 'credit';
}
