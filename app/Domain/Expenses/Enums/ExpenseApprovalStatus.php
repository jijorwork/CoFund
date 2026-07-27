<?php

namespace App\Domain\Expenses\Enums;

enum ExpenseApprovalStatus: string
{
    case NotSubmitted = 'not_submitted';
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
