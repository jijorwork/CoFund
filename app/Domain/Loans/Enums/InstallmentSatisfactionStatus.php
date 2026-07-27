<?php

namespace App\Domain\Loans\Enums;

enum InstallmentSatisfactionStatus: string
{
    case Unsatisfied = 'unsatisfied';
    case PartiallySatisfied = 'partially_satisfied';
    case Satisfied = 'satisfied';
}
