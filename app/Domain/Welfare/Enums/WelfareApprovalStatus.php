<?php

namespace App\Domain\Welfare\Enums;

enum WelfareApprovalStatus: string
{
    case Pending = 'pending';
    case PrimaryApproved = 'primary_approved';
    case SecondaryApproved = 'secondary_approved';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
