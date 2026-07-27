<?php

namespace App\Domain\Reports\Services;

use App\Domain\Organisations\Tenancy\TenantContext;
use App\Domain\Reports\ValueObjects\ReportDateRange;
use InvalidArgumentException;

class ExecuteRegisteredReport
{
    public function __construct(
        private TenantContext $context,
        private ReportScope $scope,
        private BuildGeneralLedgerReport $generalLedger,
        private BuildTrialBalanceReport $trialBalance,
        private BuildFinancialStatements $statements,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array{data: mixed, summary: array<string, mixed>, range: ReportDateRange}
     */
    public function execute(string $key, array $filters): array
    {
        $range = ReportDateRange::from(
            $this->context->organisation(),
            $this->string($filters, 'date_from'),
            $this->string($filters, 'date_to'),
            $this->string($filters, 'as_of'),
        );
        $branch = $this->scope->branch($this->string($filters, 'branch_uuid'));
        $result = match ($key) {
            'general-ledger' => $this->generalLedger->build($range, $branch, $this->string($filters, 'ledger_account_uuid')),
            'trial-balance' => $this->trialBalance->build($range, $branch, (bool) ($filters['include_zero_balances'] ?? false)),
            'statement-of-financial-position' => $this->statements->financialPosition($range, $branch),
            'income-and-expenditure' => $this->statements->incomeAndExpenditure($range, $branch),
            'cash-flow' => $this->statements->cashFlow($range, $branch),
            default => throw new InvalidArgumentException('This report uses its existing dedicated endpoint and is not yet available through generic execution.'),
        };

        return [...$result, 'range' => $range];
    }

    /** @param array<string, mixed> $values */
    private function string(array $values, string $key): ?string
    {
        $value = $values[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
