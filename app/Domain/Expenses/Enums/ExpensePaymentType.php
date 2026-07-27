<?php

namespace App\Domain\Expenses\Enums;

enum ExpensePaymentType: string
{
    case DirectExpense = 'direct_expense';
    case Reimbursement = 'reimbursement';
    case PettyCashAdvance = 'petty_cash_advance';
    case PettyCashSettlement = 'petty_cash_settlement';
    case Refund = 'refund';
    case Reversal = 'reversal';
}
