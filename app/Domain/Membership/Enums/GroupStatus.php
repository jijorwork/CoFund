<?php

namespace App\Domain\Membership\Enums;

enum GroupStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Inactive = 'inactive';
}
