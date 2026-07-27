<?php

namespace App\Domain\Accounting\Actions;

use App\Domain\Accounting\Enums\AccountCategory;
use App\Domain\Accounting\Enums\AccountPurpose;
use App\Domain\Accounting\Enums\LedgerAccountStatus;
use App\Domain\Accounting\Models\LedgerAccount;
use App\Domain\Organisations\Models\Organisation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateDefaultChartOfAccounts
{
    /** @return list<LedgerAccount> */
    public function handle(Organisation $organisation, ?User $actor = null): array
    {
        return DB::transaction(function () use ($organisation, $actor): array {
            $accounts = [];

            foreach ($this->template() as $definition) {
                $accounts[] = LedgerAccount::query()->firstOrCreate([
                    'organisation_id' => $organisation->getKey(),
                    'code' => $definition['code'],
                ], [
                    ...$definition,
                    'normal_balance' => $definition['category']->normalBalance(),
                    'currency_code' => strtoupper($organisation->currency_code),
                    'status' => LedgerAccountStatus::Active,
                    'is_system_account' => true,
                    'created_by_user_id' => $actor?->getKey(),
                    'activated_at' => now(),
                ]);
            }

            return $accounts;
        });
    }

    /**
     * @return list<array{
     *     code: string,
     *     name: string,
     *     category: AccountCategory,
     *     purpose: AccountPurpose,
     *     allows_direct_posting: bool
     * }>
     */
    private function template(): array
    {
        return [
            ['code' => '1000', 'name' => 'Cash and Cash Equivalents', 'category' => AccountCategory::Asset, 'purpose' => AccountPurpose::Cash, 'allows_direct_posting' => false],
            ['code' => '1100', 'name' => 'Office Cash', 'category' => AccountCategory::Asset, 'purpose' => AccountPurpose::Cash, 'allows_direct_posting' => true],
            ['code' => '1200', 'name' => 'Collector Cash', 'category' => AccountCategory::Asset, 'purpose' => AccountPurpose::Cash, 'allows_direct_posting' => true],
            ['code' => '1300', 'name' => 'Bank Account', 'category' => AccountCategory::Asset, 'purpose' => AccountPurpose::Bank, 'allows_direct_posting' => true],
            ['code' => '1400', 'name' => 'Mobile Money Clearing', 'category' => AccountCategory::Asset, 'purpose' => AccountPurpose::Clearing, 'allows_direct_posting' => true],
            ['code' => '1500', 'name' => 'Payment Suspense', 'category' => AccountCategory::Asset, 'purpose' => AccountPurpose::Suspense, 'allows_direct_posting' => true],
            ['code' => '1600', 'name' => 'Petty Cash', 'category' => AccountCategory::Asset, 'purpose' => AccountPurpose::PettyCash, 'allows_direct_posting' => true],
            ['code' => '1700', 'name' => 'Staff Cash Advances', 'category' => AccountCategory::Asset, 'purpose' => AccountPurpose::StaffCashAdvanceReceivable, 'allows_direct_posting' => true],
            ['code' => '1800', 'name' => 'Member Loan Receivables', 'category' => AccountCategory::Asset, 'purpose' => AccountPurpose::MemberLoanReceivable, 'allows_direct_posting' => true],
            ['code' => '2000', 'name' => 'Member Savings Liability', 'category' => AccountCategory::Liability, 'purpose' => AccountPurpose::MemberSavingsLiability, 'allows_direct_posting' => true],
            ['code' => '2100', 'name' => 'Member Welfare Liability', 'category' => AccountCategory::Liability, 'purpose' => AccountPurpose::MemberWelfareLiability, 'allows_direct_posting' => true],
            ['code' => '2200', 'name' => 'Member Refunds Payable', 'category' => AccountCategory::Liability, 'purpose' => AccountPurpose::Other, 'allows_direct_posting' => true],
            ['code' => '2300', 'name' => 'Unidentified Cash Overage', 'category' => AccountCategory::Liability, 'purpose' => AccountPurpose::CashOverageLiability, 'allows_direct_posting' => true],
            ['code' => '2400', 'name' => 'Staff Reimbursements Payable', 'category' => AccountCategory::Liability, 'purpose' => AccountPurpose::StaffReimbursementPayable, 'allows_direct_posting' => true],
            ['code' => '2500', 'name' => 'Welfare Claims Payable', 'category' => AccountCategory::Liability, 'purpose' => AccountPurpose::WelfareClaimsPayable, 'allows_direct_posting' => true],
            ['code' => '3000', 'name' => 'Opening Balance Equity', 'category' => AccountCategory::Equity, 'purpose' => AccountPurpose::Equity, 'allows_direct_posting' => true],
            ['code' => '3100', 'name' => 'Retained Surplus', 'category' => AccountCategory::Equity, 'purpose' => AccountPurpose::Equity, 'allows_direct_posting' => true],
            ['code' => '4000', 'name' => 'Administrative Fee Income', 'category' => AccountCategory::Income, 'purpose' => AccountPurpose::FeeIncome, 'allows_direct_posting' => true],
            ['code' => '4100', 'name' => 'Penalty Income', 'category' => AccountCategory::Income, 'purpose' => AccountPurpose::PenaltyIncome, 'allows_direct_posting' => true],
            ['code' => '4200', 'name' => 'Other Income', 'category' => AccountCategory::Income, 'purpose' => AccountPurpose::Other, 'allows_direct_posting' => true],
            ['code' => '4300', 'name' => 'Loan Interest Income', 'category' => AccountCategory::Income, 'purpose' => AccountPurpose::LoanInterestIncome, 'allows_direct_posting' => true],
            ['code' => '4400', 'name' => 'Loan Fee Income', 'category' => AccountCategory::Income, 'purpose' => AccountPurpose::LoanFeeIncome, 'allows_direct_posting' => true],
            ['code' => '4500', 'name' => 'Loan Penalty Income', 'category' => AccountCategory::Income, 'purpose' => AccountPurpose::LoanPenaltyIncome, 'allows_direct_posting' => true],
            ['code' => '4600', 'name' => 'Withdrawal Fee Income', 'category' => AccountCategory::Income, 'purpose' => AccountPurpose::WithdrawalFeeIncome, 'allows_direct_posting' => true],
            ['code' => '5000', 'name' => 'Operating Expenses', 'category' => AccountCategory::Expense, 'purpose' => AccountPurpose::OperatingExpense, 'allows_direct_posting' => true],
            ['code' => '5100', 'name' => 'Transaction Charges', 'category' => AccountCategory::Expense, 'purpose' => AccountPurpose::OperatingExpense, 'allows_direct_posting' => true],
            ['code' => '5200', 'name' => 'Other Expenses', 'category' => AccountCategory::Expense, 'purpose' => AccountPurpose::Other, 'allows_direct_posting' => true],
            ['code' => '5300', 'name' => 'Collector Cash Shortage Expense', 'category' => AccountCategory::Expense, 'purpose' => AccountPurpose::CashShortageExpense, 'allows_direct_posting' => true],
            ['code' => '5400', 'name' => 'Welfare Benefit Expense', 'category' => AccountCategory::Expense, 'purpose' => AccountPurpose::WelfareBenefitExpense, 'allows_direct_posting' => true],
        ];
    }
}
