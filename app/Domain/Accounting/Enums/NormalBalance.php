<?php

namespace App\Domain\Accounting\Enums;

enum NormalBalance: string
{
    case Debit = 'debit';
    case Credit = 'credit';
}
