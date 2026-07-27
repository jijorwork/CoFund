<?php

namespace App\Domain\Loans\Enums;

enum LoanSequenceType: string
{
    case Application = 'application';
    case Offer = 'offer';
    case Loan = 'loan';
    case Disbursement = 'disbursement';
    case Repayment = 'repayment';
    case Restructure = 'restructure';

    public function prefix(): string
    {
        return match ($this) {
            self::Application => 'LAP',
            self::Offer => 'LOF',
            self::Loan => 'LON',
            self::Disbursement => 'LDS',
            self::Repayment => 'LRP',
            self::Restructure => 'LRS',
        };
    }
}
