<?php

namespace App\Domain\Welfare\Enums;

enum WelfareEligibilityStatus: string
{
    case NotEvaluated = 'not_evaluated';
    case Eligible = 'eligible';
    case EligibleWithWarnings = 'eligible_with_warnings';
    case Ineligible = 'ineligible';
    case Overridden = 'overridden';
}
