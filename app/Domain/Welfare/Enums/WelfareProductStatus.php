<?php

namespace App\Domain\Welfare\Enums;

enum WelfareProductStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Suspended = 'suspended';
    case Closed = 'closed';
    case Archived = 'archived';
}
