<?php

namespace App\Domain\Accounting\Services;

use App\Domain\Accounting\Enums\EntryType;
use App\Domain\Accounting\Enums\FinancialTransactionStatus;
use App\Domain\Accounting\Models\FinancialTransaction;
use App\Domain\Accounting\Models\LedgerAccount;
use App\Domain\Accounting\Models\TransactionEntry;
use App\Domain\Organisations\Models\Branch;
use App\Domain\Organisations\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class CalculateTrialBalance
{
    public function __construct(private TenantContext $context, private AccountingScope $scope) {}

    /**
     * @return array{
     *   accounts: list<array<string, int|string>>,
     *   totals: array{
     *     currencies: array<string, array{
     *       debit_total_minor: int,
     *       credit_total_minor: int,
     *       closing_debit_minor: int,
     *       closing_credit_minor: int
     *     }>,
     *     is_balanced: bool
     *   }
     * }
     */
    public function calculate(?CarbonImmutable $asOf = null, ?Branch $branch = null): array
    {
        $this->scope->assertBranch($branch, allowOrganisationWide: true);
        $accounts = LedgerAccount::query()
            ->whereBelongsTo($this->context->organisation())
            ->where('allows_direct_posting', true)
            ->when(
                $this->scope->isBranchRestricted(),
                fn (Builder $query) => $query->where(fn (Builder $query) => $query
                    ->whereNull('branch_id')
                    ->orWhere('branch_id', $this->context->branch()?->getKey())),
            )
            ->orderBy('code')
            ->get();
        $transactionTable = (new FinancialTransaction)->getTable();
        $entryTable = (new TransactionEntry)->getTable();
        $totals = TransactionEntry::query()
            ->join(
                $transactionTable,
                $transactionTable.'.id',
                '=',
                $entryTable.'.financial_transaction_id',
            )
            ->where($entryTable.'.organisation_id', $this->context->organisation()->getKey())
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
            ))
            ->when(
                $this->scope->isBranchRestricted() && $branch === null,
                fn (Builder $query) => $query->where(
                    $transactionTable.'.branch_id',
                    $this->context->branch()?->getKey(),
                ),
            )
            ->groupBy($entryTable.'.ledger_account_id')
            ->select($entryTable.'.ledger_account_id as account_id')
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN entry_type = ? THEN amount_minor ELSE 0 END), 0) as debit_total,
                 COALESCE(SUM(CASE WHEN entry_type = ? THEN amount_minor ELSE 0 END), 0) as credit_total',
                [EntryType::Debit->value, EntryType::Credit->value],
            )
            ->get()
            ->keyBy('account_id');
        $currencyTotals = [];

        $rows = array_values($accounts->map(function (LedgerAccount $account) use ($totals, &$currencyTotals): array {
            $total = $totals->get($account->getKey());
            $debits = (int) $total?->getAttribute('debit_total');
            $credits = (int) $total?->getAttribute('credit_total');
            $net = $debits - $credits;
            $closingDebit = max(0, $net);
            $closingCredit = max(0, -$net);
            $currencyTotals[$account->currency_code] ??= [
                'debit_total_minor' => 0,
                'credit_total_minor' => 0,
                'closing_debit_minor' => 0,
                'closing_credit_minor' => 0,
            ];
            $currencyTotals[$account->currency_code]['debit_total_minor'] += $debits;
            $currencyTotals[$account->currency_code]['credit_total_minor'] += $credits;
            $currencyTotals[$account->currency_code]['closing_debit_minor'] += $closingDebit;
            $currencyTotals[$account->currency_code]['closing_credit_minor'] += $closingCredit;

            return [
                'uuid' => $account->public_id,
                'code' => $account->code,
                'name' => $account->name,
                'category' => $account->category->value,
                'debit_total_minor' => $debits,
                'credit_total_minor' => $credits,
                'closing_debit_minor' => $closingDebit,
                'closing_credit_minor' => $closingCredit,
                'currency_code' => $account->currency_code,
            ];
        })->values()->all());

        return [
            'accounts' => $rows,
            'totals' => [
                'currencies' => $currencyTotals,
                'is_balanced' => collect($currencyTotals)->every(
                    fn (array $total): bool => $total['debit_total_minor'] === $total['credit_total_minor'],
                ),
            ],
        ];
    }
}
