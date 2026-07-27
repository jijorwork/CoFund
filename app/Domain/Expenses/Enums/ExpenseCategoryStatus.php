<?php

namespace App\Domain\Expenses\Enums;

enum ExpenseCategoryStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Archived = 'archived';
}
