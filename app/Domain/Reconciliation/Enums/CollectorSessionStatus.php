<?php

namespace App\Domain\Reconciliation\Enums;

enum CollectorSessionStatus: string
{
    case Open = 'open';
    case Closed = 'closed';
    case Submitted = 'submitted';
    case UnderReview = 'under_review';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Reversed = 'reversed';
    case Cancelled = 'cancelled';
}
