<?php

namespace App\Domain\Membership\Enums;

enum MemberStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Suspended = 'suspended';
    case Inactive = 'inactive';
    case Exited = 'exited';
    case Deceased = 'deceased';

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Active, self::Deceased],
            self::Active => [self::Suspended, self::Inactive, self::Exited, self::Deceased],
            self::Suspended => [self::Active, self::Deceased],
            self::Inactive => [self::Active, self::Deceased],
            self::Exited => [self::Deceased],
            self::Deceased => [],
        };
    }
}
