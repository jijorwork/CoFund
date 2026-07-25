<?php

namespace App\Domain\Membership\Enums;

enum DocumentVerificationStatus: string
{
    case Pending = 'pending';
    case Verified = 'verified';
    case Rejected = 'rejected';
}
