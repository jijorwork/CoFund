<?php

namespace App\Domain\Reconciliation\Enums;

enum CashDepositStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Confirmed = 'confirmed';
    case Rejected = 'rejected';
    case Reversed = 'reversed';
    case Cancelled = 'cancelled';
}
