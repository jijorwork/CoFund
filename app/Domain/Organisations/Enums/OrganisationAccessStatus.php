<?php

namespace App\Domain\Organisations\Enums;

enum OrganisationAccessStatus: string
{
    case Invited = 'invited';
    case Active = 'active';
    case Suspended = 'suspended';
    case Revoked = 'revoked';

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Invited => [self::Active, self::Revoked],
            self::Active => [self::Suspended, self::Revoked],
            self::Suspended => [self::Active, self::Revoked],
            self::Revoked => [],
        };
    }
}
