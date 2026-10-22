<?php

namespace App\Domain\Approvals\Enums;

enum ApprovalDecisionOutcome: string
{
    case Approved = 'approved';
    case Rejected = 'rejected';
}
