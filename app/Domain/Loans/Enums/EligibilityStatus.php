<?php

namespace App\Domain\Loans\Enums;

enum EligibilityStatus: string
{
    case NotEvaluated = 'not_evaluated';
    case Eligible = 'eligible';
    case Ineligible = 'ineligible';
    case EligibleWithWarnings = 'eligible_with_warnings';
}
