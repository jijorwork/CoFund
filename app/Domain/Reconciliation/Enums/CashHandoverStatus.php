<?php

namespace App\Domain\Reconciliation\Enums;

enum CashHandoverStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Received = 'received';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Reversed = 'reversed';
    case Cancelled = 'cancelled';
}
