<?php

namespace App\Domain\Collections\Enums;

enum PaymentAllocationStatus: string
{
    case Active = 'active';
    case Reversed = 'reversed';
}
