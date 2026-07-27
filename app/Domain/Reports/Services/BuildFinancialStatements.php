<?php

namespace App\Domain\Reports\Services;

use App\Domain\Accounting\Enums\AccountCategory;
use App\Domain\Accounting\Enums\AccountPurpose;
use App\Domain\Accounting\Enums\EntryType;
use App\Domain\Accounting\Enums\FinancialTransactionStatus;
use App\Domain\Accounting\Models\FinancialTransaction;
use App\Domain\Accounting\Models\LedgerAccount;
use App\Domain\Accounting\Models\TransactionEntry;
use App\Domain\Organisations\Models\Branch;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Domain\Reports\ValueObjects\ReportDateRange;
use Illuminate\Database\Eloquent\Builder;

class BuildFinancialStatements
{
    public function __construct(
        private BuildTrialBalanceReport $trialBalance,
        private TenantContext $context,
    ) {}

    /** @return array{data: array<string, mixed>, summary: array<string, mixed>} */
    public function financialPosition(ReportDateRange $range, ?Branch $branch): array
    {
        $trial = $this->trialBalance->build($range, $branch, true);
        $sections = ['assets' => [], 'liabilities' => [], 'equity' => []];
        $totals = ['assets_minor' => 0, 'liabilities_minor' => 0, 'equity_minor' => 0];

        foreach ($trial['data'] as $row) {
            $category = $row['account_type'];
            if (! in_array($category, [
                AccountCategory::Asset->value,
                AccountCategory::Liability->value,
                AccountCategory::Equity->value,
            ], true)) {
                continue;
            }
            $amount = $category === AccountCategory::Asset->value
                ? $row['closing_debit_minor'] - $row['closing_credit_minor']
                : $row['closing_credit_minor'] - $row['closing_debit_minor'];
            $section = match ($category) {
                AccountCategory::Asset->value => 'assets',
                AccountCategory::Liability->value => 'liabilities',
                default => 'equity',
            };
            $sections[$section][] = [...$row, 'amount_minor' => $amount];
            $totals[$section.'_minor'] += $amount;
        }

        $incomeAndExpenditure = $this->incomeAndExpenditure($range, $branch);
        $unclosedIncome = (int) collect($trial['data'])
            ->where('account_type', AccountCategory::Income->value)
            ->sum(fn (array $row): int => $row['closing_credit_minor'] - $row['closing_debit_minor']);
        $unclosedExpenses = (int) collect($trial['data'])
            ->where('account_type', AccountCategory::Expense->value)
            ->sum(fn (array $row): int => $row['closing_debit_minor'] - $row['closing_credit_minor']);
        $totals['current_period_surplus_minor'] = $incomeAndExpenditure['summary']['surplus_or_deficit_minor'];
        $totals['unclosed_surplus_minor'] = $unclosedIncome - $unclosedExpenses;
        $totals['liabilities_and_equity_minor'] = $totals['liabilities_minor'] + $totals['equity_minor'] + $totals['unclosed_surplus_minor'];
        $totals['difference_minor'] = $totals['assets_minor'] - $totals['liabilities_and_equity_minor'];

        return ['data' => $sections, 'summary' => $totals];
    }

    /** @return array{data: array<string, list<array<string, mixed>>>, summary: array<string, int>} */
    public function incomeAndExpenditure(ReportDateRange $range, ?Branch $branch): array
    {
        $trial = $this->trialBalance->build($range, $branch, true);
        $sections = ['income' => [], 'expenses' => []];
        $summary = ['income_minor' => 0, 'expenses_minor' => 0, 'surplus_or_deficit_minor' => 0];

        foreach ($trial['data'] as $row) {
            $category = $row['account_type'];
            if ($category === AccountCategory::Income->value) {
                $amount = $row['period_credit_minor'] - $row['period_debit_minor'];
                $sections['income'][] = [...$row, 'amount_minor' => $amount];
                $summary['income_minor'] += $amount;
            }
            if ($category === AccountCategory::Expense->value) {
                $amount = $row['period_debit_minor'] - $row['period_credit_minor'];
                $sections['expenses'][] = [...$row, 'amount_minor' => $amount];
                $summary['expenses_minor'] += $amount;
            }
        }
        $summary['surplus_or_deficit_minor'] = $summary['income_minor'] - $summary['expenses_minor'];

        return ['data' => $sections, 'summary' => $summary];
    }

    /** @return array{data: array<string, mixed>, summary: array<string, int>} */
    public function cashFlow(ReportDateRange $range, ?Branch $branch): array
    {
        $trial = $this->trialBalance->build($range, $branch, true);
        $liquidPurposes = [
            AccountPurpose::Cash->value,
            AccountPurpose::Bank->value,
            AccountPurpose::MobileMoney->value,
            AccountPurpose::PettyCash->value,
            AccountPurpose::Clearing->value,
        ];
        $rows = collect($trial['data'])
            ->filter(fn (array $row): bool => in_array($row['account_purpose'], $liquidPurposes, true))
            ->values()
            ->all();
        $opening = (int) collect($rows)->sum(fn (array $row): int => $row['opening_debit_minor'] - $row['opening_credit_minor']);
        $entryTable = (new TransactionEntry)->getTable();
        $transactionTable = (new FinancialTransaction)->getTable();
        $accountTable = (new LedgerAccount)->getTable();
        $movements = TransactionEntry::query()
            ->join($transactionTable, $transactionTable.'.id', '=', $entryTable.'.financial_transaction_id')
            ->join($accountTable, $accountTable.'.id', '=', $entryTable.'.ledger_account_id')
            ->where($entryTable.'.organisation_id', $this->context->organisation()->getKey())
            ->whereIn($transactionTable.'.status', [
                FinancialTransactionStatus::Posted->value,
                FinancialTransactionStatus::Reversed->value,
            ])
            ->whereBetween($transactionTable.'.transaction_date', [$range->localStart->toDateString(), $range->localEnd->toDateString()])
            ->whereIn($accountTable.'.purpose', $liquidPurposes)
            ->when($branch, fn (Builder $query) => $query->where($transactionTable.'.branch_id', $branch->getKey()))
            ->groupBy($entryTable.'.financial_transaction_id')
            ->select($entryTable.'.financial_transaction_id')
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN '.$entryTable.'.entry_type = ? THEN '.$entryTable.'.amount_minor ELSE -'.$entryTable.'.amount_minor END), 0) net_liquid_movement_minor',
                [EntryType::Debit->value],
            )
            ->get();
        $inflows = (int) $movements->sum(fn ($row): int => max(0, (int) $row->getAttribute('net_liquid_movement_minor')));
        $outflows = (int) $movements->sum(fn ($row): int => max(0, -(int) $row->getAttribute('net_liquid_movement_minor')));
        $closing = $opening + $inflows - $outflows;

        return [
            'data' => ['liquid_accounts' => $rows],
            'summary' => [
                'opening_liquid_balance_minor' => $opening,
                'total_inflows_minor' => $inflows,
                'total_outflows_minor' => $outflows,
                'net_cash_movement_minor' => $inflows - $outflows,
                'closing_liquid_balance_minor' => $closing,
            ],
        ];
    }
}
