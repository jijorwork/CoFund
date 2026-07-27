<?php

namespace App\Domain\Collections\Enums;

enum ContributionPaymentStatus: string
{
    case Posted = 'posted';
    case PartiallyAllocated = 'partially_allocated';
    case Allocated = 'allocated';
    case Unallocated = 'unallocated';
    case Reversed = 'reversed';
}
