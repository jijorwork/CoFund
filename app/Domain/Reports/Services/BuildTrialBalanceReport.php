<?php

namespace App\Domain\Reports\Services;

use App\Domain\Accounting\Enums\EntryType;
use App\Domain\Accounting\Enums\FinancialTransactionStatus;
use App\Domain\Accounting\Models\FinancialTransaction;
use App\Domain\Accounting\Models\LedgerAccount;
use App\Domain\Accounting\Models\TransactionEntry;
use App\Domain\Organisations\Models\Branch;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Domain\Reports\ValueObjects\ReportDateRange;
use Illuminate\Database\Eloquent\Builder;

class BuildTrialBalanceReport
{
    public function __construct(private TenantContext $context) {}

    /** @return array{data: list<array<string, mixed>>, summary: array<string, mixed>} */
    public function build(ReportDateRange $range, ?Branch $branch, bool $includeZeroBalances = false): array
    {
        $entryTable = (new TransactionEntry)->getTable();
        $transactionTable = (new FinancialTransaction)->getTable();
        $totals = TransactionEntry::query()
            ->join($transactionTable, $transactionTable.'.id', '=', $entryTable.'.financial_transaction_id')
            ->where($entryTable.'.organisation_id', $this->context->organisation()->getKey())
            ->whereIn($transactionTable.'.status', [
                FinancialTransactionStatus::Posted->value,
                FinancialTransactionStatus::Reversed->value,
            ])
            ->whereDate($transactionTable.'.transaction_date', '<=', $range->localEnd->toDateString())
            ->when($branch, fn (Builder $query) => $query->where($transactionTable.'.branch_id', $branch->getKey()))
            ->groupBy($entryTable.'.ledger_account_id')
            ->select($entryTable.'.ledger_account_id')
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN financial_transactions.transaction_date < ? AND transaction_entries.entry_type = ? THEN transaction_entries.amount_minor ELSE 0 END), 0) opening_debit,
                 COALESCE(SUM(CASE WHEN financial_transactions.transaction_date < ? AND transaction_entries.entry_type = ? THEN transaction_entries.amount_minor ELSE 0 END), 0) opening_credit,
                 COALESCE(SUM(CASE WHEN financial_transactions.transaction_date BETWEEN ? AND ? AND transaction_entries.entry_type = ? THEN transaction_entries.amount_minor ELSE 0 END), 0) period_debit,
                 COALESCE(SUM(CASE WHEN financial_transactions.transaction_date BETWEEN ? AND ? AND transaction_entries.entry_type = ? THEN transaction_entries.amount_minor ELSE 0 END), 0) period_credit',
                [
                    $range->localStart->toDateString(), EntryType::Debit->value,
                    $range->localStart->toDateString(), EntryType::Credit->value,
                    $range->localStart->toDateString(), $range->localEnd->toDateString(), EntryType::Debit->value,
                    $range->localStart->toDateString(), $range->localEnd->toDateString(), EntryType::Credit->value,
                ],
            )
            ->get()
            ->keyBy('ledger_account_id');

        $summary = [
            'opening_debit_minor' => 0, 'opening_credit_minor' => 0,
            'period_debit_minor' => 0, 'period_credit_minor' => 0,
            'closing_debit_minor' => 0, 'closing_credit_minor' => 0,
        ];
        $rows = array_values(LedgerAccount::query()
            ->whereBelongsTo($this->context->organisation())
            ->when($branch, fn (Builder $query) => $query->where(fn (Builder $query) => $query->whereNull('branch_id')->orWhere('branch_id', $branch->getKey())))
            ->orderBy('code')
            ->get()
            ->map(function (LedgerAccount $account) use ($totals, &$summary): array {
                $total = $totals->get($account->getKey());
                $openingDebit = (int) $total?->getAttribute('opening_debit');
                $openingCredit = (int) $total?->getAttribute('opening_credit');
                $periodDebit = (int) $total?->getAttribute('period_debit');
                $periodCredit = (int) $total?->getAttribute('period_credit');
                $closingNet = $openingDebit + $periodDebit - $openingCredit - $periodCredit;
                $closingDebit = max(0, $closingNet);
                $closingCredit = max(0, -$closingNet);
                $summary['opening_debit_minor'] += $openingDebit;
                $summary['opening_credit_minor'] += $openingCredit;
                $summary['period_debit_minor'] += $periodDebit;
                $summary['period_credit_minor'] += $periodCredit;
                $summary['closing_debit_minor'] += $closingDebit;
                $summary['closing_credit_minor'] += $closingCredit;

                return [
                    'account_uuid' => $account->public_id,
                    'account_code' => $account->code,
                    'account_name' => $account->name,
                    'account_type' => $account->category->value,
                    'account_purpose' => $account->purpose->value,
                    'opening_debit_minor' => $openingDebit,
                    'opening_credit_minor' => $openingCredit,
                    'period_debit_minor' => $periodDebit,
                    'period_credit_minor' => $periodCredit,
                    'closing_debit_minor' => $closingDebit,
                    'closing_credit_minor' => $closingCredit,
                    'currency_code' => $account->currency_code,
                ];
            })
            ->filter(fn (array $row): bool => $includeZeroBalances || array_sum([
                $row['opening_debit_minor'], $row['opening_credit_minor'],
                $row['period_debit_minor'], $row['period_credit_minor'],
            ]) > 0)
            ->values()
            ->all());
        $summary['difference_minor'] = $summary['closing_debit_minor'] - $summary['closing_credit_minor'];
        $summary['is_balanced'] = $summary['difference_minor'] === 0;

        return ['data' => $rows, 'summary' => $summary];
    }
}
