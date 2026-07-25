<?php

namespace App\Domain\Identity\Enums;

enum SystemRole: string
{
    case PlatformAdmin = 'platform_admin';
    case OrganisationAdmin = 'organisation_admin';
    case BranchManager = 'branch_manager';
    case Treasurer = 'treasurer';
    case Collector = 'collector';
    case LoanOfficer = 'loan_officer';
    case Approver = 'approver';
    case Auditor = 'auditor';
    case Member = 'member';

    public function label(): string
    {
        return match ($this) {
            self::PlatformAdmin => 'Platform Administrator',
            self::OrganisationAdmin => 'Organisation Administrator',
            self::BranchManager => 'Branch Manager',
            self::Treasurer => 'Treasurer',
            self::Collector => 'Collector',
            self::LoanOfficer => 'Loan Officer',
            self::Approver => 'Approver',
            self::Auditor => 'Auditor',
            self::Member => 'Member',
        };
    }

    public function isOrganisationAssignable(): bool
    {
        return $this !== self::PlatformAdmin;
    }
}
