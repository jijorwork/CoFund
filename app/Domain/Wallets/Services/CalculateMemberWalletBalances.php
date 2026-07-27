<?php

namespace App\Domain\Wallets\Services;

use App\Domain\Accounting\Data\AccountBalance;
use App\Domain\Accounting\Enums\EntryType;
use App\Domain\Accounting\Enums\FinancialTransactionStatus;
use App\Domain\Accounting\Models\FinancialTransaction;
use App\Domain\Accounting\Models\TransactionEntry;
use App\Domain\Accounting\Services\AccountingScope;
use App\Domain\Accounting\Services\CalculateLedgerAccountBalance;
use App\Domain\Membership\Models\Member;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Domain\Wallets\Models\Wallet;
use Carbon\CarbonImmutable;

class CalculateMemberWalletBalances
{
    public function __construct(private TenantContext $context, private AccountingScope $scope) {}

    /** @return list<array{wallet: Wallet, balance: AccountBalance}> */
    public function calculate(Member $member, ?CarbonImmutable $asOf = null): array
    {
        if ($member->organisation_id !== $this->context->organisation()->getKey()) {
            return [];
        }

        $walletQuery = Wallet::query()
            ->whereBelongsTo($this->context->organisation())
            ->where('walletable_type', 'member')
            ->where('walletable_id', $member->getKey())
            ->with('ledgerAccount');
        $this->scope->apply($walletQuery);
        $wallets = $walletQuery->get();

        if ($wallets->isEmpty()) {
            return [];
        }

        $transactionTable = (new FinancialTransaction)->getTable();
        $totals = TransactionEntry::query()
            ->join(
                $transactionTable,
                $transactionTable.'.id',
                '=',
                (new TransactionEntry)->qualifyColumn('financial_transaction_id'),
            )
            ->whereIn((new TransactionEntry)->qualifyColumn('wallet_id'), $wallets->modelKeys())
            ->whereIn($transactionTable.'.status', [
                FinancialTransactionStatus::Posted->value,
                FinancialTransactionStatus::Reversed->value,
            ])
            ->when($asOf, fn ($query) => $query->whereDate(
                $transactionTable.'.transaction_date',
                '<=',
                $asOf->toDateString(),
            ))
            ->groupBy((new TransactionEntry)->qualifyColumn('wallet_id'))
            ->selectRaw(
                'wallet_id,
                 COALESCE(SUM(CASE WHEN entry_type = ? THEN amount_minor ELSE 0 END), 0) as debit_total,
                 COALESCE(SUM(CASE WHEN entry_type = ? THEN amount_minor ELSE 0 END), 0) as credit_total',
                [EntryType::Debit->value, EntryType::Credit->value],
            )
            ->get()
            ->keyBy('wallet_id');

        return array_values($wallets->map(function (Wallet $wallet) use ($totals): array {
            $total = $totals->get($wallet->getKey());

            return [
                'wallet' => $wallet,
                'balance' => CalculateLedgerAccountBalance::fromTotals(
                    $wallet->ledgerAccount->normal_balance,
                    (int) $total?->getAttribute('debit_total'),
                    (int) $total?->getAttribute('credit_total'),
                ),
            ];
        })->values()->all());
    }
}
