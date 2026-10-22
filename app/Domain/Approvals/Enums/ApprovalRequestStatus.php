<?php

namespace App\Domain\Approvals\Enums;

enum ApprovalRequestStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
