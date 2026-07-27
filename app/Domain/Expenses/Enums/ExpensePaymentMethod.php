<?php

namespace App\Domain\Expenses\Enums;

enum ExpensePaymentMethod: string
{
    case Cash = 'cash';
    case BankTransfer = 'bank_transfer';
    case MobileMoneyManual = 'mobile_money_manual';
    case Cheque = 'cheque';
    case InternalTransfer = 'internal_transfer';
}
