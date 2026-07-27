<?php

namespace App\Domain\Reconciliation\Enums;

enum CashDeclarationStatus: string
{
    case Active = 'active';
    case Superseded = 'superseded';
    case Cancelled = 'cancelled';
}
