<?php

namespace App\Domain\Accounting\Enums;

enum AccountPurpose: string
{
    case Cash = 'cash';
    case Bank = 'bank';
    case MobileMoney = 'mobile_money';
    case MemberSavingsLiability = 'member_savings_liability';
    case MemberWelfareLiability = 'member_welfare_liability';
    case MemberLoanReceivable = 'member_loan_receivable';
    case LoanInterestIncome = 'loan_interest_income';
    case LoanFeeIncome = 'loan_fee_income';
    case LoanPenaltyIncome = 'loan_penalty_income';
    case ContributionIncome = 'contribution_income';
    case FeeIncome = 'fee_income';
    case PenaltyIncome = 'penalty_income';
    case OperatingExpense = 'operating_expense';
    case PettyCash = 'petty_cash';
    case Suspense = 'suspense';
    case Clearing = 'clearing';
    case Equity = 'equity';
    case Other = 'other';
    case CashOverageLiability = 'cash_overage_liability';
    case CashShortageExpense = 'cash_shortage_expense';
    case StaffCashAdvanceReceivable = 'staff_cash_advance_receivable';
    case StaffReimbursementPayable = 'staff_reimbursement_payable';
    case WelfareClaimsPayable = 'welfare_claims_payable';
    case WelfareBenefitExpense = 'welfare_benefit_expense';
    case WithdrawalFeeIncome = 'withdrawal_fee_income';
}
