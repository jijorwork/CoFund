<?php

namespace App\Domain\Accounting\Enums;

enum FinancialReferenceType: string
{
    case ContributionPayment = 'contribution_payment';
    case Expense = 'expense';
    case Loan = 'loan';
    case WelfareClaim = 'welfare_claim';
    case OpeningBalance = 'opening_balance';
    case Adjustment = 'adjustment';
    case Transaction = 'transaction';
    case CollectorCashHandover = 'collector_cash_handover';
    case CashDeposit = 'cash_deposit';
    case CashVariance = 'cash_variance';
    case ExpensePayment = 'expense_payment';
    case PettyCashAdvance = 'petty_cash_advance';
    case PettyCashRetirement = 'petty_cash_retirement';
}
