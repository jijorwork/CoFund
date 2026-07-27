<?php

namespace App\Domain\Welfare\Enums;

enum WelfareClaimStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case UnderReview = 'under_review';
    case PendingDocuments = 'pending_documents';
    case PendingVerification = 'pending_verification';
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case Settled = 'settled';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
    case Reversed = 'reversed';
    case Closed = 'closed';
}
