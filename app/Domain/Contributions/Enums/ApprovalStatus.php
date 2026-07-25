<?php

namespace App\Domain\Contributions\Enums;

enum ApprovalStatus: string
{
    case NotRequired = 'not_required';
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
