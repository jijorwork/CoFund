<?php

namespace App\Domain\Welfare\Enums;

enum WelfarePaymentStatus: string
{
    case NotPayable = 'not_payable';
    case Unpaid = 'unpaid';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case Reversed = 'reversed';
}
