<?php

namespace App\Domain\Expenses\Enums;

enum PettyCashAdvanceStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Disbursed = 'disbursed';
    case PartiallyRetired = 'partially_retired';
    case Retired = 'retired';
    case Overdue = 'overdue';
    case Cancelled = 'cancelled';
    case Rejected = 'rejected';
    case Reversed = 'reversed';
}
