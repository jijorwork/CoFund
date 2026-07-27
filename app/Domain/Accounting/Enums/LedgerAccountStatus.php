<?php

namespace App\Domain\Accounting\Enums;

enum LedgerAccountStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Suspended = 'suspended';
    case Closed = 'closed';
    case Archived = 'archived';
}
