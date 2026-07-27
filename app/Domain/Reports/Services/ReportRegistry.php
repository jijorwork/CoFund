<?php

namespace App\Domain\Reports\Services;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Reports\ValueObjects\ReportDefinition;
use InvalidArgumentException;

class ReportRegistry
{
    /** @var array<string, ReportDefinition>|null */
    private ?array $definitions = null;

    /** @return array<string, ReportDefinition> */
    public function all(): array
    {
        if ($this->definitions !== null) {
            return $this->definitions;
        }

        $commonFilters = ['as_of', 'date_from', 'date_to', 'branch_uuid', 'group_uuid', 'member_uuid', 'product_uuid', 'wallet_uuid', 'ledger_account_uuid', 'currency_code', 'status', 'include_zero_balances'];
        $commonSorts = ['date', '-date', 'amount', '-amount', 'reference', '-reference'];

        $groups = [
            FoundationPermission::ReportsContributionsView->value => [
                'contributions', 'contribution-obligations', 'contribution-arrears',
                'contribution-product-performance', 'member-contribution-statements',
            ],
            FoundationPermission::ReportsCollectionsView->value => [
                'collections', 'receipts', 'collection-methods', 'reversed-receipts',
            ],
            FoundationPermission::ReportsCollectorsView->value => [
                'collector-sessions', 'collector-performance', 'collector-variances',
                'cash-handovers', 'cash-deposits', 'unreconciled-collections',
            ],
            FoundationPermission::ReportsFinancialView->value => [
                'general-ledger', 'trial-balance', 'account-balances', 'journal',
                'financial-transactions', 'reversals', 'accounting-periods',
                'suspense-balances', 'statement-of-financial-position',
                'income-and-expenditure', 'cash-flow', 'wallet-balances',
                'wallet-movements', 'cash-position', 'internal-transfers', 'financial-health',
            ],
            FoundationPermission::ReportsExpensesView->value => [
                'expenses', 'expense-approvals', 'expense-payments', 'expenses-by-category',
                'unpaid-approved-expenses', 'petty-cash-advances', 'petty-cash-retirements',
                'outstanding-petty-cash', 'petty-cash-variances',
            ],
            FoundationPermission::ReportsLoansView->value => [
                'loan-portfolio', 'loan-applications', 'loan-disbursements', 'loan-repayments',
                'loan-arrears', 'loan-ageing', 'loan-product-performance',
                'guarantor-exposure', 'loan-settlements',
            ],
            FoundationPermission::ReportsWelfareView->value => [
                'welfare-enrolments', 'welfare-claims', 'welfare-benefits',
                'welfare-outstanding-payables', 'welfare-product-performance',
                'welfare-beneficiary-payments', 'welfare-eligibility-rejections',
            ],
            FoundationPermission::ReportsWithdrawalsView->value => [
                'withdrawal-requests', 'withdrawal-payouts', 'withdrawal-fees',
                'outstanding-withdrawals', 'savings-holds', 'withdrawal-rejections',
                'withdrawal-restrictions', 'withdrawal-product-performance',
            ],
            FoundationPermission::ReportsMembersView->value => [
                'groups', 'group-financial-summary', 'group-member-performance',
            ],
            FoundationPermission::ReportsAuditView->value => [
                'audit-log', 'financial-reversals', 'approval-history',
                'override-history', 'role-switch-activity', 'sequence-gaps',
                'pending-approvals',
            ],
            FoundationPermission::ReportsDataIntegrityView->value => ['data-integrity'],
        ];

        $definitions = [];
        foreach ($groups as $permission => $keys) {
            foreach ($keys as $key) {
                $executable = in_array($key, [
                    'general-ledger',
                    'trial-balance',
                    'statement-of-financial-position',
                    'income-and-expenditure',
                    'cash-flow',
                ], true);
                $definitions[$key] = new ReportDefinition(
                    key: $key,
                    name: str($key)->replace('-', ' ')->title()->toString(),
                    description: 'Tenant-scoped CoFund '.str($key)->replace('-', ' ')->toString().' report.',
                    permission: $permission,
                    filters: $commonFilters,
                    sorting: $commonSorts,
                    grouping: ['branch', 'group', 'product', 'status', 'date'],
                    columns: [],
                    exportFormats: $executable ? ['csv'] : [],
                );
            }
        }

        return $this->definitions = $definitions;
    }

    public function get(string $key): ReportDefinition
    {
        return $this->all()[$key] ?? throw new InvalidArgumentException('Unknown report key.');
    }

    public function has(string $key): bool
    {
        return isset($this->all()[$key]);
    }
}
