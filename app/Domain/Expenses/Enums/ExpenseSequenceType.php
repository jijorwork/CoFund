<?php

namespace App\Domain\Expenses\Enums;

enum ExpenseSequenceType: string
{
    case Expense = 'EXP';
    case Payment = 'EPM';
    case Advance = 'PCA';
    case Retirement = 'PCR';
}
