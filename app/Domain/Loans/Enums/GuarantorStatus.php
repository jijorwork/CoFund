<?php

namespace App\Domain\Loans\Enums;

enum GuarantorStatus: string
{
    case Invited = 'invited';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Revoked = 'revoked';
    case Released = 'released';
}
