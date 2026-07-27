<?php

namespace App\Domain\Collections\Enums;

enum ReceiptType: string
{
    case ContributionPayment = 'contribution_payment';
    case ContributionReversal = 'contribution_reversal';
    case LoanRepayment = 'loan_repayment';
    case WelfareBenefitPayment = 'welfare_benefit_payment';
    case WithdrawalPayout = 'withdrawal_payout';
}
