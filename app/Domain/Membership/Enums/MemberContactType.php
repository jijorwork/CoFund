<?php

namespace App\Domain\Membership\Enums;

enum MemberContactType: string
{
    case Beneficiary = 'beneficiary';
    case NextOfKin = 'next_of_kin';
    case EmergencyContact = 'emergency_contact';
}
