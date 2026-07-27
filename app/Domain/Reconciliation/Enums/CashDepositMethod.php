<?php

namespace App\Domain\Reconciliation\Enums;

enum CashDepositMethod: string
{
    case BankCashDeposit = 'bank_cash_deposit';
    case BankTransfer = 'bank_transfer';
    case MobileMoneyTransfer = 'mobile_money_transfer';
    case InternalCashTransfer = 'internal_cash_transfer';
}
