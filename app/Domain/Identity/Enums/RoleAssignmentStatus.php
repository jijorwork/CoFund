<?php

namespace App\Domain\Identity\Enums;

enum RoleAssignmentStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Suspended = 'suspended';
    case Revoked = 'revoked';
    case Expired = 'expired';
}
