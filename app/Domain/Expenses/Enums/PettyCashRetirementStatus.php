<?php

namespace App\Domain\Expenses\Enums;

enum PettyCashRetirementStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case UnderReview = 'under_review';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Reversed = 'reversed';
}
