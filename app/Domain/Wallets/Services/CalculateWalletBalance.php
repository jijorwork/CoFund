<?php

namespace App\Domain\Wallets\Services;

use App\Domain\Accounting\Data\AccountBalance;
use App\Domain\Accounting\Enums\EntryType;
use App\Domain\Accounting\Enums\FinancialTransactionStatus;
use App\Domain\Accounting\Models\FinancialTransaction;
use App\Domain\Accounting\Models\TransactionEntry;
use App\Domain\Accounting\Services\CalculateLedgerAccountBalance;
use App\Domain\Wallets\Exceptions\InvalidWalletOperation;
use App\Domain\Wallets\Models\Wallet;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class CalculateWalletBalance
{
    public function calculate(Wallet $wallet, ?CarbonImmutable $asOf = null): AccountBalance
    {
        if (
            $wallet->ledgerAccount->organisation_id !== $wallet->organisation_id
            || $wallet->ledgerAccount->currency_code !== $wallet->currency_code
        ) {
            throw new InvalidWalletOperation;
        }

        $transactionTable = (new FinancialTransaction)->getTable();
        $totals = TransactionEntry::query()
            ->join(
                $transactionTable,
                $transactionTable.'.id',
                '=',
                (new TransactionEntry)->qualifyColumn('financial_transaction_id'),
            )
            ->where((new TransactionEntry)->qualifyColumn('wallet_id'), $wallet->getKey())
            ->whereIn($transactionTable.'.status', [
                FinancialTransactionStatus::Posted->value,
                FinancialTransactionStatus::Reversed->value,
            ])
            ->when($asOf, fn (Builder $query) => $query->whereDate(
                $transactionTable.'.transaction_date',
                '<=',
                $asOf->toDateString(),
            ))
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN entry_type = ? THEN amount_minor ELSE 0 END), 0) as debit_total,
                 COALESCE(SUM(CASE WHEN entry_type = ? THEN amount_minor ELSE 0 END), 0) as credit_total',
                [EntryType::Debit->value, EntryType::Credit->value],
            )
            ->first();

        return CalculateLedgerAccountBalance::fromTotals(
            $wallet->ledgerAccount->normal_balance,
            (int) $totals?->getAttribute('debit_total'),
            (int) $totals?->getAttribute('credit_total'),
        );
    }
}
