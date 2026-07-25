<?php

namespace App\Domain\Contributions\Enums;

enum ContributionPlanType: string
{
    case RegularSavings = 'regular_savings';
    case TargetSavings = 'target_savings';
    case WelfareContribution = 'welfare_contribution';
    case FlexibleSavings = 'flexible_savings';
    case RotationalSusu = 'rotational_susu';
}
