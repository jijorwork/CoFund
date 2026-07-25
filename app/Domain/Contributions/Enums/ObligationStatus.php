<?php

namespace App\Domain\Contributions\Enums;

enum ObligationStatus: string
{
    case Upcoming = 'upcoming';
    case Due = 'due';
    case Overdue = 'overdue';
    case PartiallySatisfied = 'partially_satisfied';
    case Satisfied = 'satisfied';
    case Waived = 'waived';
    case Cancelled = 'cancelled';
}
