<?php

namespace App\Domain\Organisations\Enums;

enum OrganisationStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Suspended = 'suspended';
    case Inactive = 'inactive';
}
