<?php

namespace App\Domain\Loans\Services;

use App\Domain\Loans\Enums\GuarantorStatus;
use App\Domain\Loans\Enums\LoanPerformanceStatus;
use App\Domain\Loans\Enums\LoanStatus;
use App\Domain\Loans\Models\Loan;
use App\Domain\Loans\Models\LoanApplication;
use App\Domain\Loans\Models\LoanGuarantor;
use App\Domain\Membership\Enums\MemberStatus;
use App\Domain\Membership\Models\Member;
use App\Domain\Wallets\Enums\WalletType;
use App\Domain\Wallets\Services\CalculateMemberWalletBalances;

class EvaluateGuarantorEligibility
{
    public function __construct(private CalculateMemberWalletBalances $balances) {}

    /** @return array{eligible:bool,blocking_reasons:list<string>,warnings:list<string>,existing_exposure_minor:int,remaining_capacity_minor:int} */
    public function evaluate(Member $guarantor, LoanApplication $application, int $amountMinor): array
    {
        $reasons = [];
        $savings = collect($this->balances->calculate($guarantor))->filter(fn (array $item): bool => $item['wallet']->wallet_type === WalletType::MemberSavings)->sum(fn (array $item): int => max(0, $item['balance']->displayBalanceMinor));
        $exposure = (int) LoanGuarantor::query()
            ->where('organisation_id', $guarantor->organisation_id)
            ->where('guarantor_member_id', $guarantor->getKey())
            ->where('loan_application_id', '!=', $application->getKey())
            ->where('status', GuarantorStatus::Accepted)
            ->sum('guaranteed_amount_minor');
        $capacity = intdiv($savings * $application->product->guarantee_limit_basis_points, 10000);
        $overdue = Loan::query()->where('organisation_id', $guarantor->organisation_id)->where('member_id', $guarantor->getKey())->whereIn('status', [LoanStatus::Active->value, LoanStatus::Suspended->value])->whereIn('performance_status', [LoanPerformanceStatus::Overdue->value, LoanPerformanceStatus::NonPerforming->value])->exists();

        if ($guarantor->getKey() === $application->member_id) {
            $reasons[] = 'borrower_cannot_guarantee_own_loan';
        }
        if ($guarantor->organisation_id !== $application->organisation_id) {
            $reasons[] = 'guarantor_outside_organisation';
        }
        if ($guarantor->status !== MemberStatus::Active) {
            $reasons[] = 'guarantor_not_active';
        }
        if ($application->product->branch_id !== null && $guarantor->branch_id !== $application->branch_id) {
            $reasons[] = 'guarantor_branch_incompatible';
        }
        if ($overdue) {
            $reasons[] = 'guarantor_has_overdue_loan';
        }
        if ($amountMinor > max(0, $capacity - $exposure)) {
            $reasons[] = 'guarantee_capacity_exceeded';
        }

        return ['eligible' => $reasons === [], 'blocking_reasons' => $reasons, 'warnings' => [], 'existing_exposure_minor' => $exposure, 'remaining_capacity_minor' => max(0, $capacity - $exposure)];
    }
}
