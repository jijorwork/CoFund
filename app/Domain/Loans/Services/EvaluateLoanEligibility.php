<?php

namespace App\Domain\Loans\Services;

use App\Domain\Collections\Enums\ContributionPaymentStatus;
use App\Domain\Collections\Models\ContributionPayment;
use App\Domain\Contributions\Enums\EnrolmentStatus;
use App\Domain\Contributions\Models\MemberPlanEnrolment;
use App\Domain\Loans\Enums\LoanPerformanceStatus;
use App\Domain\Loans\Enums\LoanStatus;
use App\Domain\Loans\Models\Loan;
use App\Domain\Loans\Models\LoanApplication;
use App\Domain\Loans\Models\LoanProduct;
use App\Domain\Membership\Enums\MemberStatus;
use App\Domain\Membership\Models\Member;
use App\Domain\Wallets\Enums\WalletType;
use App\Domain\Wallets\Services\CalculateMemberWalletBalances;

class EvaluateLoanEligibility
{
    public function __construct(private CalculateMemberWalletBalances $walletBalances) {}

    /** @return array{eligible:bool,blocking_reasons:list<string>,warnings:list<string>,maximum_eligible_principal_minor:int,required_guarantor_count:int,collateral_required:bool,savings_summary:array{qualifying_minor:int},contribution_summary:array{count:int,total_minor:int,active_enrolment:bool},current_loan_summary:array{active_count:int,overdue_count:int,outstanding_minor:int}} */
    public function evaluate(Member $member, LoanProduct $product, int $requestedPrincipalMinor, int $requestedTerm, ?int $applicationId = null): array
    {
        $reasons = [];
        $warnings = [];
        $payments = ContributionPayment::query()
            ->where('organisation_id', $member->organisation_id)
            ->where('member_id', $member->getKey())
            ->whereIn('status', [ContributionPaymentStatus::Posted->value, ContributionPaymentStatus::Allocated->value, ContributionPaymentStatus::PartiallyAllocated->value]);
        $contributionCount = (clone $payments)->count();
        $contributionTotal = (int) (clone $payments)->sum('amount_minor');
        $activeEnrolment = MemberPlanEnrolment::query()->where('organisation_id', $member->organisation_id)->where('member_id', $member->getKey())->where('status', EnrolmentStatus::Active)->exists();
        $savings = collect($this->walletBalances->calculate($member))
            ->filter(fn (array $item): bool => $item['wallet']->wallet_type === WalletType::MemberSavings)
            ->sum(fn (array $item): int => max(0, $item['balance']->displayBalanceMinor));
        $activeLoans = Loan::query()->where('organisation_id', $member->organisation_id)->where('member_id', $member->getKey())->when($applicationId, fn ($query) => $query->where('loan_application_id', '!=', $applicationId))->whereIn('status', [LoanStatus::PendingDisbursement->value, LoanStatus::Active->value, LoanStatus::Suspended->value])->get();
        $overdueCount = $activeLoans->whereIn('performance_status', [LoanPerformanceStatus::Overdue, LoanPerformanceStatus::NonPerforming])->count();
        $ratioMaximum = $product->maximum_loan_to_savings_basis_points === null ? $product->maximum_amount_minor : intdiv($savings * $product->maximum_loan_to_savings_basis_points, 10000);
        $maximum = min($product->maximum_amount_minor, $ratioMaximum);

        if ($member->status !== MemberStatus::Active) {
            $reasons[] = 'member_not_active';
        }
        if ($member->joined_at->diffInDays(now(), absolute: true) < $product->minimum_membership_days) {
            $reasons[] = 'membership_duration_too_short';
        }
        if ($contributionCount < $product->minimum_contribution_count) {
            $reasons[] = 'insufficient_contribution_count';
        }
        if ($contributionTotal < $product->minimum_contribution_value_minor) {
            $reasons[] = 'insufficient_contribution_value';
        }
        if (! $activeEnrolment) {
            $reasons[] = 'no_active_contribution_enrolment';
        }
        if ($requestedPrincipalMinor < $product->minimum_amount_minor || $requestedPrincipalMinor > $maximum) {
            $reasons[] = 'principal_outside_eligible_limit';
        }
        if ($requestedTerm < $product->minimum_term || $requestedTerm > $product->maximum_term) {
            $reasons[] = 'term_outside_product_limit';
        }
        if ($activeLoans->count() >= $product->maximum_active_loans) {
            $reasons[] = 'maximum_active_loans_reached';
        }
        if ($product->blocks_overdue_borrowers && $overdueCount > 0) {
            $reasons[] = 'overdue_loan_exists';
        }
        if ($product->branch_id !== null && $product->branch_id !== $member->branch_id) {
            $reasons[] = 'branch_not_eligible';
        }
        if ($requestedPrincipalMinor > max(0, $member->getAttribute('declared_monthly_income_minor') ?? PHP_INT_MAX)) {
            $warnings[] = 'declared_affordability_requires_review';
        }

        return ['eligible' => $reasons === [], 'blocking_reasons' => $reasons, 'warnings' => $warnings, 'maximum_eligible_principal_minor' => max(0, $maximum), 'required_guarantor_count' => $product->required_guarantor_count, 'collateral_required' => $product->requires_collateral, 'savings_summary' => ['qualifying_minor' => $savings], 'contribution_summary' => ['count' => $contributionCount, 'total_minor' => $contributionTotal, 'active_enrolment' => $activeEnrolment], 'current_loan_summary' => ['active_count' => $activeLoans->count(), 'overdue_count' => $overdueCount, 'outstanding_minor' => (int) $activeLoans->sum(fn (Loan $loan): int => $loan->outstanding_principal_minor)]];
    }

    /** @return array<string,mixed> */
    public function forApplication(LoanApplication $application): array
    {
        return $this->evaluate($application->member, $application->product, $application->requested_principal_minor, $application->requested_term, $application->getKey());
    }
}
