<?php

namespace App\Domain\Loans\Enums;

enum LoanProductStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Suspended = 'suspended';
    case Closed = 'closed';
    case Archived = 'archived';
}
