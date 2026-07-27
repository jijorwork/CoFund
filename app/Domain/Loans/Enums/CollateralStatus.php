<?php

namespace App\Domain\Loans\Enums;

enum CollateralStatus: string
{
    case Proposed = 'proposed';
    case UnderReview = 'under_review';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Released = 'released';
}
