<?php

namespace App\Domain\Loans\Enums;

enum LoanPerformanceStatus: string
{
    case NotDisbursed = 'not_disbursed';
    case Current = 'current';
    case InGrace = 'in_grace';
    case Overdue = 'overdue';
    case NonPerforming = 'non_performing';
    case Settled = 'settled';
}
