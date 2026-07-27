<?php

namespace App\Domain\Collections\Enums;

enum ContributionPaymentMethod: string
{
    case Cash = 'cash';
    case BankTransfer = 'bank_transfer';
    case MobileMoneyManual = 'mobile_money_manual';
    case Cheque = 'cheque';
    case CardManual = 'card_manual';
    case Adjustment = 'adjustment';
}
