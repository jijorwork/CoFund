<?php

namespace App\Domain\Membership\Enums;

enum MemberGender: string
{
    case Female = 'female';
    case Male = 'male';
    case Other = 'other';
    case PreferNotToSay = 'prefer_not_to_say';
}
