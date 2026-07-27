<?php

namespace App\Domain\Wallets\Enums;

use App\Domain\Accounting\Enums\AccountPurpose;

enum WalletType: string
{
    case MemberSavings = 'member_savings';
    case MemberLoan = 'member_loan';
    case OfficeCash = 'office_cash';
    case CollectorCash = 'collector_cash';
    case Bank = 'bank';
    case MobileMoney = 'mobile_money';
    case Suspense = 'suspense';
    case PettyCash = 'petty_cash';

    public function accountPurpose(): AccountPurpose
    {
        return match ($this) {
            self::MemberSavings => AccountPurpose::MemberSavingsLiability,
            self::MemberLoan => AccountPurpose::MemberLoanReceivable,
            self::OfficeCash, self::CollectorCash => AccountPurpose::Cash,
            self::Bank => AccountPurpose::Bank,
            self::MobileMoney => AccountPurpose::Clearing,
            self::Suspense => AccountPurpose::Suspense,
            self::PettyCash => AccountPurpose::PettyCash,
        };
    }
}
