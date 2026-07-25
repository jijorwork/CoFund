<?php

namespace App\Domain\Organisations\Enums;

enum BranchStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Inactive = 'inactive';
}
