<?php

namespace App\Domain\Membership\Enums;

enum MemberContactStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Revoked = 'revoked';
}
