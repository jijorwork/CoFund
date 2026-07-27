<?php

namespace App\Domain\Collections\Enums;

enum PaymentAllocationStrategy: string
{
    case OldestDueFirst = 'oldest_due_first';
    case SelectedObligations = 'selected_obligations';
    case CurrentPeriodFirst = 'current_period_first';
    case LeaveUnallocated = 'leave_unallocated';
}
