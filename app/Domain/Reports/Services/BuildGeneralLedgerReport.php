<?php

namespace App\Domain\Reports\Services;

use App\Domain\Accounting\Enums\EntryType;
use App\Domain\Accounting\Enums\FinancialTransactionStatus;
use App\Domain\Accounting\Enums\NormalBalance;
use App\Domain\Accounting\Models\FinancialTransaction;
use App\Domain\Accounting\Models\LedgerAccount;
use App\Domain\Accounting\Models\TransactionEntry;
use App\Domain\Organisations\Models\Branch;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Domain\Reports\ValueObjects\ReportDateRange;
use Illuminate\Database\Eloquent\Builder;

class BuildGeneralLedgerReport
{
    public function __construct(private TenantContext $context) {}

    /** @return array{data: list<array<string, mixed>>, summary: array<string, int>} */
    public function build(ReportDateRange $range, ?Branch $branch, ?string $accountUuid = null): array
    {
        $entryTable = (new TransactionEntry)->getTable();
        $transactionTable = (new FinancialTransaction)->getTable();
        $opening = TransactionEntry::query()
            ->join($transactionTable, $transactionTable.'.id', '=', $entryTable.'.financial_transaction_id')
            ->where($entryTable.'.organisation_id', $this->context->organisation()->getKey())
            ->whereIn($transactionTable.'.status', [
                FinancialTransactionStatus::Posted->value,
                FinancialTransactionStatus::Reversed->value,
            ])
            ->whereDate($transactionTable.'.transaction_date', '<', $range->localStart->toDateString())
            ->when($branch, fn (Builder $query) => $query->where($transactionTable.'.branch_id', $branch->getKey()))
            ->when($accountUuid, fn (Builder $query) => $query->whereHas('ledgerAccount', fn (Builder $query) => $query->where('public_id', $accountUuid)))
            ->groupBy($entryTable.'.ledger_account_id')
            ->select($entryTable.'.ledger_account_id')
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN transaction_entries.entry_type = ? THEN transaction_entries.amount_minor ELSE -transaction_entries.amount_minor END), 0) net_minor',
                [EntryType::Debit->value],
            )
            ->get()
            ->keyBy('ledger_account_id');
        $entries = TransactionEntry::query()
            ->where($entryTable.'.organisation_id', $this->context->organisation()->getKey())
            ->whereHas('transaction', fn (Builder $query) => $query
                ->whereIn('status', [FinancialTransactionStatus::Posted, FinancialTransactionStatus::Reversed])
                ->whereBetween('transaction_date', [$range->localStart->toDateString(), $range->localEnd->toDateString()])
                ->when($branch, fn (Builder $query) => $query->where('branch_id', $branch->getKey())))
            ->when($accountUuid, fn (Builder $query) => $query->whereHas('ledgerAccount', fn (Builder $query) => $query->where('public_id', $accountUuid)))
            ->with(['transaction', 'ledgerAccount', 'transaction.branch'])
            ->join($transactionTable, $transactionTable.'.id', '=', $entryTable.'.financial_transaction_id')
            ->orderBy($transactionTable.'.transaction_date')
            ->orderBy($transactionTable.'.posted_at')
            ->orderBy($transactionTable.'.transaction_number')
            ->orderBy($entryTable.'.sequence_number')
            ->select($entryTable.'.*')
            ->limit(10000)
            ->get();

        $balances = [];
        $openingAccounts = LedgerAccount::query()->whereKey($opening->keys())->get()->keyBy('id');
        foreach ($opening as $accountId => $row) {
            $account = $openingAccounts->get($accountId);
            if ($account !== null) {
                $net = (int) $row->getAttribute('net_minor');
                $balances[$account->getKey()] = $account->normal_balance === NormalBalance::Debit ? $net : -$net;
            }
        }
        $summary = ['debit_minor' => 0, 'credit_minor' => 0];
        $rows = array_values($entries->map(function (TransactionEntry $entry) use (&$balances, &$summary): array {
            $account = $entry->ledgerAccount;
            $debit = $entry->entry_type === EntryType::Debit ? (int) $entry->amount_minor : 0;
            $credit = $entry->entry_type === EntryType::Credit ? (int) $entry->amount_minor : 0;
            $movement = $account->normal_balance === NormalBalance::Debit ? $debit - $credit : $credit - $debit;
            $balances[$account->getKey()] = ($balances[$account->getKey()] ?? 0) + $movement;
            $summary['debit_minor'] += $debit;
            $summary['credit_minor'] += $credit;

            return [
                'entry_uuid' => $entry->public_id,
                'transaction_uuid' => $entry->transaction->public_id,
                'transaction_number' => $entry->transaction->transaction_number,
                'transaction_date' => $entry->transaction->transaction_date->toDateString(),
                'posted_at' => $entry->transaction->posted_at?->toIso8601String(),
                'transaction_type' => $entry->transaction->transaction_type->value,
                'description' => $entry->description ?? $entry->transaction->description,
                'account' => ['uuid' => $account->public_id, 'code' => $account->code, 'name' => $account->name],
                'debit_minor' => $debit,
                'credit_minor' => $credit,
                'running_balance_minor' => $balances[$account->getKey()],
                'currency_code' => $entry->currency_code,
                'branch_uuid' => $entry->transaction->branch?->public_id,
                'source' => ['type' => $entry->transaction->reference_type?->value, 'uuid' => $entry->transaction->reference_id],
                'reversal_status' => $entry->transaction->status->value,
            ];
        })->all());
        $summary['difference_minor'] = $summary['debit_minor'] - $summary['credit_minor'];

        return ['data' => $rows, 'summary' => $summary];
    }
}
