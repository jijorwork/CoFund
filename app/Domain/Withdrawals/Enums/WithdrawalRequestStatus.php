<?php

namespace App\Domain\Withdrawals\Enums;

enum WithdrawalRequestStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case UnderReview = 'under_review';
    case PendingApproval = 'pending_approval';
    case PendingSecondaryApproval = 'pending_secondary_approval';
    case Approved = 'approved';
    case PartiallyPaid = 'partially_paid';
    case Settled = 'settled';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
    case Reversed = 'reversed';
}
