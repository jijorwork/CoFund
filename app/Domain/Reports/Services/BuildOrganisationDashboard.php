<?php

namespace App\Domain\Reports\Services;

use App\Domain\Collections\Models\ContributionPayment;
use App\Domain\Contributions\Models\ContributionObligation;
use App\Domain\Expenses\Models\ExpenseRequest;
use App\Domain\Expenses\Models\PettyCashAdvance;
use App\Domain\Loans\Models\Loan;
use App\Domain\Loans\Models\LoanRepayment;
use App\Domain\Membership\Models\Member;
use App\Domain\Organisations\Models\Branch;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Domain\Reports\ValueObjects\ReportDateRange;
use App\Domain\Welfare\Models\WelfareBenefitPayment;
use App\Domain\Welfare\Models\WelfareClaim;
use App\Domain\Withdrawals\Models\SavingsHold;
use App\Domain\Withdrawals\Models\WithdrawalPayout;
use App\Domain\Withdrawals\Models\WithdrawalRequest;
use Illuminate\Database\Eloquent\Builder;

class BuildOrganisationDashboard
{
    public function __construct(
        private TenantContext $context,
        private BuildTrialBalanceReport $trialBalance,
        private CalculateReportRatio $ratios,
    ) {}

    /** @return array<string, mixed> */
    public function build(ReportDateRange $range, ?Branch $branch = null): array
    {
        $members = $this->scope(Member::query(), $branch);
        $obligations = ContributionObligation::query()
            ->where('organisation_id', $this->context->organisation()->getKey())
            ->when($branch, fn (Builder $query) => $query->whereHas('member', fn (Builder $query) => $query->where('branch_id', $branch->getKey())));
        $payments = $this->scope(ContributionPayment::query()->where('status', 'posted'), $branch)
            ->whereBetween('payment_date', [$range->localStart->toDateString(), $range->localEnd->toDateString()]);
        $obligationTotals = (clone $obligations)
            ->whereBetween('due_date', [$range->localStart->toDateString(), $range->localEnd->toDateString()])
            ->selectRaw('COALESCE(SUM(expected_amount_minor),0) due_minor, COALESCE(SUM(satisfied_amount_minor),0) paid_minor, COALESCE(SUM(outstanding_amount_minor),0) outstanding_minor')
            ->first();
        $collected = (int) (clone $payments)->sum('amount_minor');
        $due = (int) $obligationTotals?->getAttribute('due_minor');
        $trial = $this->trialBalance->build($range, $branch, true);
        $accountTotals = collect($trial['data'])->groupBy('account_type')->map(
            fn ($rows): int => (int) $rows->sum(fn (array $row): int => match ($row['account_type']) {
                'asset', 'expense' => $row['closing_debit_minor'] - $row['closing_credit_minor'],
                default => $row['closing_credit_minor'] - $row['closing_debit_minor'],
            }),
        );

        $expenses = $this->scope(ExpenseRequest::query(), $branch);
        $loans = $this->scope(Loan::query(), $branch);
        $claims = $this->scope(WelfareClaim::query(), $branch);
        $withdrawals = $this->scope(WithdrawalRequest::query(), $branch);

        return [
            'membership' => [
                'total_members' => (clone $members)->count(),
                'active_members' => (clone $members)->where('status', 'active')->count(),
                'suspended_members' => (clone $members)->where('status', 'suspended')->count(),
                'new_members' => (clone $members)->whereBetween('joined_at', [$range->localStart, $range->localEnd])->count(),
            ],
            'contributions' => [
                'collected_minor' => $collected,
                'obligations_minor' => $due,
                'outstanding_obligations_minor' => (int) $obligationTotals?->getAttribute('outstanding_minor'),
                'members_in_arrears' => (clone $obligations)->where('outstanding_amount_minor', '>', 0)->distinct('member_id')->count('member_id'),
                'collection_rate' => $this->ratios->calculate($collected, $due),
            ],
            'expenses' => [
                'approved_minor' => (int) (clone $expenses)->whereBetween('approved_at', [$range->utcStart, $range->utcEnd])->sum('approved_amount_minor'),
                'paid_minor' => (int) (clone $expenses)->whereBetween('completed_at', [$range->utcStart, $range->utcEnd])->sum('paid_amount_minor'),
                'pending_approval_count' => (clone $expenses)->whereIn('status', ['submitted', 'under_review', 'pending_approval', 'pending_secondary_approval'])->count(),
                'petty_cash_outstanding_minor' => (int) $this->scope(PettyCashAdvance::query(), $branch)->selectRaw('COALESCE(SUM(disbursed_amount_minor - retired_amount_minor),0) amount')->value('amount'),
            ],
            'loans' => [
                'active_count' => (clone $loans)->where('status', 'active')->count(),
                'outstanding_principal_minor' => (int) (clone $loans)->sum('outstanding_principal_minor'),
                'arrears_minor' => (int) (clone $loans)->sum('arrears_minor'),
                'repayments_minor' => (int) $this->scope(LoanRepayment::query()->where('status', 'posted'), $branch)
                    ->whereBetween('repayment_date', [$range->localStart->toDateString(), $range->localEnd->toDateString()])
                    ->sum('amount_minor'),
            ],
            'welfare' => [
                'claims_submitted' => (clone $claims)->whereBetween('submitted_at', [$range->utcStart, $range->utcEnd])->count(),
                'claims_pending_approval' => (clone $claims)->whereIn('status', ['submitted', 'under_review', 'verified', 'pending_approval', 'pending_secondary_approval'])->count(),
                'benefits_paid_minor' => (int) $this->scope(WelfareBenefitPayment::query()->where('status', 'posted'), $branch)
                    ->whereBetween('payment_date', [$range->localStart->toDateString(), $range->localEnd->toDateString()])
                    ->sum('amount_minor'),
                'outstanding_payable_minor' => (int) (clone $claims)->sum('outstanding_payable_minor'),
            ],
            'withdrawals' => [
                'requests_submitted' => (clone $withdrawals)->whereBetween('submitted_at', [$range->utcStart, $range->utcEnd])->count(),
                'approved_awaiting_payout_minor' => (int) (clone $withdrawals)->sum('outstanding_approved_minor'),
                'gross_paid_minor' => (int) $this->scope(WithdrawalPayout::query()->where('status', 'posted'), $branch)
                    ->whereBetween('payout_date', [$range->localStart->toDateString(), $range->localEnd->toDateString()])
                    ->sum('gross_withdrawal_minor'),
                'active_holds_minor' => (int) $this->scope(SavingsHold::query()->where('status', 'active'), $branch)->sum('amount_minor'),
            ],
            'accounting' => [
                'total_assets_minor' => (int) ($accountTotals['asset'] ?? 0),
                'total_liabilities_minor' => (int) ($accountTotals['liability'] ?? 0),
                'period_income_minor' => (int) collect($trial['data'])->where('account_type', 'income')->sum(fn (array $row): int => $row['period_credit_minor'] - $row['period_debit_minor']),
                'period_expenses_minor' => (int) collect($trial['data'])->where('account_type', 'expense')->sum(fn (array $row): int => $row['period_debit_minor'] - $row['period_credit_minor']),
                'trial_balance_difference_minor' => $trial['summary']['difference_minor'],
            ],
        ];
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    private function scope(Builder $query, ?Branch $branch): Builder
    {
        return $query
            ->where('organisation_id', $this->context->organisation()->getKey())
            ->when($branch, fn (Builder $query) => $query->where('branch_id', $branch->getKey()));
    }
}
