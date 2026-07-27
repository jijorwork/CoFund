<?php

namespace App\Domain\Loans\Enums;

enum LoanRepaymentComponent: string
{
    case Penalty = 'penalty';
    case Fee = 'fee';
    case Interest = 'interest';
    case Principal = 'principal';
}
