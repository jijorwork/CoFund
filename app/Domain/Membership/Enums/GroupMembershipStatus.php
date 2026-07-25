<?php

namespace App\Domain\Membership\Enums;

enum GroupMembershipStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Left = 'left';
}
