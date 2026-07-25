<?php

namespace App\Domain\Membership\Enums;

enum MemberDocumentType: string
{
    case ProfilePhoto = 'profile_photo';
    case NationalId = 'national_id';
    case Passport = 'passport';
    case MembershipForm = 'membership_form';
    case BeneficiaryForm = 'beneficiary_form';
    case NextOfKinForm = 'next_of_kin_form';
    case ConsentForm = 'consent_form';
    case SupportingDocument = 'supporting_document';
    case Other = 'other';
}
