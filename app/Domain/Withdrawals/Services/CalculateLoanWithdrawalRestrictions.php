<?php

namespace App\Domain\Withdrawals\Services;

use App\Domain\Loans\Enums\LoanPerformanceStatus;
use App\Domain\Loans\Enums\LoanStatus;
use App\Domain\Loans\Models\Loan;
use App\Domain\Membership\Models\Member;
use App\Domain\Withdrawals\Models\SavingsHold;

class CalculateLoanWithdrawalRestrictions
{
    /** @return array<string, mixed> */
    public function calculate(Member $member, string $policy): array
    {
        $loans = Loan::query()->where('organisation_id', $member->organisation_id)->where('member_id', $member->getKey())->whereIn('status', [LoanStatus::PendingDisbursement->value, LoanStatus::Active->value, LoanStatus::Suspended->value])->get();
        $overdue = $loans->whereIn('performance_status', [LoanPerformanceStatus::Overdue, LoanPerformanceStatus::NonPerforming]);
        $principal = (int) $loans->sum('outstanding_principal_minor');
        $held = (int) SavingsHold::query()->where('organisation_id', $member->organisation_id)->where('member_id', $member->getKey())->where('hold_type', 'loan_security')->where('status', 'active')->sum('amount_minor');
        $blocked = ($policy === 'block_if_any_active_loan' && $loans->isNotEmpty()) || ($policy === 'block_if_overdue_loan' && $overdue->isNotEmpty());
        $required = in_array($policy, ['retain_outstanding_principal', 'retain_required_security_only'], true) ? $principal : 0;

        return ['blocked' => $blocked, 'restricted_amount_minor' => max(0, $required - $held), 'active_loan_count' => $loans->count(), 'overdue_loan_count' => $overdue->count(), 'outstanding_principal_minor' => $principal, 'loan_security_required_minor' => $required, 'loan_security_held_minor' => $held, 'blocking_reasons' => $blocked ? ['loan_policy_blocks_withdrawal'] : [], 'warnings' => [], 'affected_loans' => $loans->map(fn (Loan $loan): array => ['uuid' => $loan->public_id, 'loan_number' => $loan->loan_number, 'outstanding_principal_minor' => $loan->outstanding_principal_minor, 'performance_status' => $loan->performance_status->value])->all()];
    }
}
