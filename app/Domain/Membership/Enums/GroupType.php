<?php

namespace App\Domain\Membership\Enums;

enum GroupType: string
{
    case SusuCircle = 'susu_circle';
    case WelfareGroup = 'welfare_group';
    case Department = 'department';
    case CollectorRoute = 'collector_route';
    case CommunityUnit = 'community_unit';
    case MemberCategory = 'member_category';
    case Other = 'other';
}
