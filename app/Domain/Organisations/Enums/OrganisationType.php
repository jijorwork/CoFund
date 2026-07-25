<?php

namespace App\Domain\Organisations\Enums;

enum OrganisationType: string
{
    case Susu = 'susu';
    case Welfare = 'welfare';
    case Cooperative = 'cooperative';
    case Church = 'church';
    case TransportUnion = 'transport_union';
    case StaffAssociation = 'staff_association';
    case CommunityGroup = 'community_group';
    case Other = 'other';
}
