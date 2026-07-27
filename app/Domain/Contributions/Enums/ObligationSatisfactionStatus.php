<?php

namespace App\Domain\Contributions\Enums;

enum ObligationSatisfactionStatus: string
{
    case Unsatisfied = 'unsatisfied';
    case PartiallySatisfied = 'partially_satisfied';
    case Satisfied = 'satisfied';
}
