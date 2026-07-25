<?php

namespace App\Domain\Organisations\Enums;

enum OrganisationAccessStatus: string
{
    case Invited = 'invited';
    case Active = 'active';
    case Suspended = 'suspended';
    case Revoked = 'revoked';
}
