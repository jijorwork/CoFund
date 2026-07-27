<?php

namespace App\Domain\Loans\Enums;

enum LoanApplicationStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case UnderReview = 'under_review';
    case PendingGuarantorAcceptance = 'pending_guarantor_acceptance';
    case PendingCollateralReview = 'pending_collateral_review';
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
    case OfferIssued = 'offer_issued';
    case OfferAccepted = 'offer_accepted';
    case Disbursed = 'disbursed';
    case Expired = 'expired';
}
