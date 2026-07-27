<?php

namespace App\Domain\Loans\Enums;

enum LoanOfferStatus: string
{
    case Issued = 'issued';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Expired = 'expired';
    case Superseded = 'superseded';
    case Cancelled = 'cancelled';
}
