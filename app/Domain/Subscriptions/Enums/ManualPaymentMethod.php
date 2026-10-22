<?php

namespace App\Domain\Subscriptions\Enums;

enum ManualPaymentMethod: string
{
    case BankTransfer = 'bank_transfer';
    case MobileMoney = 'mobile_money';
    case Cash = 'cash';
    case Cheque = 'cheque';
}
