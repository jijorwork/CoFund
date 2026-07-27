<?php

namespace App\Domain\Expenses\Enums;

enum ExpenseApprovalDecision: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Skipped = 'skipped';
    case Cancelled = 'cancelled';
}
