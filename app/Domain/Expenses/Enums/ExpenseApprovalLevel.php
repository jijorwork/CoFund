<?php

namespace App\Domain\Expenses\Enums;

enum ExpenseApprovalLevel: string
{
    case Review = 'review';
    case Primary = 'primary';
    case Secondary = 'secondary';
}
