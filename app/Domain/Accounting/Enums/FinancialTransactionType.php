<?php

namespace App\Domain\Accounting\Enums;

enum FinancialTransactionType: string
{
    case OpeningBalance = 'opening_balance';
    case ContributionPayment = 'contribution_payment';
    case ContributionReversal = 'contribution_reversal';
    case WalletTransfer = 'wallet_transfer';
    case WalletTransferReversal = 'wallet_transfer_reversal';
    case Fee = 'fee';
    case FeeReversal = 'fee_reversal';
    case Penalty = 'penalty';
    case PenaltyReversal = 'penalty_reversal';
    case Expense = 'expense';
    case ExpenseReversal = 'expense_reversal';
    case LoanDisbursement = 'loan_disbursement';
    case LoanRepayment = 'loan_repayment';
    case WelfarePayment = 'welfare_payment';
    case WelfareLiabilityRecognition = 'welfare_liability_recognition';
    case Withdrawal = 'withdrawal';
    case Refund = 'refund';
    case Adjustment = 'adjustment';
    case Reversal = 'reversal';
    case CollectorCashHandover = 'collector_cash_handover';
    case CashDeposit = 'cash_deposit';
    case CashShortageWriteOff = 'cash_shortage_write_off';
    case ExpensePayment = 'expense_payment';
    case PettyCashAdvance = 'petty_cash_advance';
    case PettyCashRetirement = 'petty_cash_retirement';
}
