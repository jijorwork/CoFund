<?php

namespace App\Domain\Notifications\Enums;

enum NotificationCategory: string
{
    case Account = 'account';
    case Membership = 'membership';
    case Contributions = 'contributions';
    case Collections = 'collections';
    case Reconciliation = 'reconciliation';
    case Expenses = 'expenses';
    case PettyCash = 'petty_cash';
    case Loans = 'loans';
    case Welfare = 'welfare';
    case Withdrawals = 'withdrawals';
    case Approvals = 'approvals';
    case Accounting = 'accounting';
    case Reports = 'reports';
    case Security = 'security';
    case System = 'system';
    case Audit = 'audit';
}
