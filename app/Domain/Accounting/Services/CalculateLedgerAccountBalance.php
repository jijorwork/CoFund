<?php

namespace App\Domain\Accounting\Services;

use App\Domain\Accounting\Data\AccountBalance;
use App\Domain\Accounting\Enums\EntryType;
use App\Domain\Accounting\Enums\FinancialTransactionStatus;
use App\Domain\Accounting\Enums\NormalBalance;
use App\Domain\Accounting\Models\FinancialTransaction;
use App\Domain\Accounting\Models\LedgerAccount;
use App\Domain\Accounting\Models\TransactionEntry;
use App\Domain\Organisations\Models\Branch;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class CalculateLedgerAccountBalance
{
    public function calculate(
        LedgerAccount $account,
        ?CarbonImmutable $asOf = null,
        ?Branch $branch = null,
    ): AccountBalance {
        $transactionTable = (new FinancialTransaction)->getTable();
        $query = TransactionEntry::query()
            ->join(
                $transactionTable,
                $transactionTable.'.id',
                '=',
                (new TransactionEntry)->qualifyColumn('financial_transaction_id'),
            )
            ->where((new TransactionEntry)->qualifyColumn('ledger_account_id'), $account->getKey())
            ->whereIn($transactionTable.'.status', [
                FinancialTransactionStatus::Posted->value,
                FinancialTransactionStatus::Reversed->value,
            ])
            ->when($asOf, fn (Builder $query) => $query->whereDate(
                $transactionTable.'.transaction_date',
                '<=',
                $asOf->toDateString(),
            ))
            ->when($branch, fn (Builder $query) => $query->where(
                $transactionTable.'.branch_id',
                $branch->getKey(),
            ));

        $totals = $query->selectRaw(
            'COALESCE(SUM(CASE WHEN entry_type = ? THEN amount_minor ELSE 0 END), 0) as debit_total,
             COALESCE(SUM(CASE WHEN entry_type = ? THEN amount_minor ELSE 0 END), 0) as credit_total',
            [EntryType::Debit->value, EntryType::Credit->value],
        )->first();
        $debits = (int) $totals?->getAttribute('debit_total');
        $credits = (int) $totals?->getAttribute('credit_total');

        return self::fromTotals($account->normal_balance, $debits, $credits);
    }

    public static function fromTotals(NormalBalance $normalBalance, int $debits, int $credits): AccountBalance
    {
        $signed = $debits - $credits;

        return new AccountBalance(
            debitTotalMinor: $debits,
            creditTotalMinor: $credits,
            signedBalanceMinor: $signed,
            normalBalance: $normalBalance,
            displayBalanceMinor: $normalBalance === NormalBalance::Debit ? $signed : -$signed,
        );
    }
}
