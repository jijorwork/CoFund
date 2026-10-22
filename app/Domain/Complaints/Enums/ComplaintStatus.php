<?php

namespace App\Domain\Complaints\Enums;

enum ComplaintStatus: string
{
    case Open = 'open';
    case InReview = 'in_review';
    case Resolved = 'resolved';
    case Closed = 'closed';
}
