<?php

namespace App\Domain\Collections\Enums;

enum ReceiptStatus: string
{
    case Issued = 'issued';
    case Reversed = 'reversed';
    case Voided = 'voided';
}
