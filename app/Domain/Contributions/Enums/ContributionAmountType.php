<?php

namespace App\Domain\Contributions\Enums;

enum ContributionAmountType: string
{
    case Fixed = 'fixed';
    case MemberSelected = 'member_selected';
    case Flexible = 'flexible';
}
