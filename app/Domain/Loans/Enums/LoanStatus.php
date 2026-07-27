<?php

namespace App\Domain\Loans\Enums;

enum LoanStatus: string
{
    case PendingDisbursement = 'pending_disbursement';
    case Active = 'active';
    case Suspended = 'suspended';
    case Settled = 'settled';
    case Closed = 'closed';
    case Restructured = 'restructured';
    case WrittenOff = 'written_off';
    case Reversed = 'reversed';
}
