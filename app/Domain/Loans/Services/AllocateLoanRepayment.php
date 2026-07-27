<?php

namespace App\Domain\Loans\Services;

use App\Domain\Loans\Enums\InstallmentSatisfactionStatus;
use App\Domain\Loans\Enums\LoanRepaymentComponent;
use App\Domain\Loans\Exceptions\InvalidLoanOperation;
use App\Domain\Loans\Models\Loan;
use App\Domain\Loans\Models\LoanRepayment;
use App\Domain\Loans\Models\LoanRepaymentAllocation;
use App\Domain\Loans\Models\LoanRepaymentInstallment;
use Carbon\CarbonImmutable;

class AllocateLoanRepayment
{
    /** @return array{principal:int,interest:int,fee:int,penalty:int,unallocated:int} */
    public function allocate(LoanRepayment $repayment, Loan $loan, int $amountMinor, CarbonImmutable $date): array
    {
        $remaining = $amountMinor;
        $totals = ['principal' => 0, 'interest' => 0, 'fee' => 0, 'penalty' => 0, 'unallocated' => 0];
        $installments = LoanRepaymentInstallment::query()->where('loan_id', $loan->getKey())->where('schedule_status', 'active')->orderBy('due_date')->orderBy('sequence_number')->lockForUpdate()->get();
        $ordered = $installments->sortBy(fn (LoanRepaymentInstallment $item): string => ($item->grace_due_date->lt($date) ? '0' : ($item->due_date->lte($date) ? '1' : '2')).str_pad((string) $item->sequence_number, 6, '0', STR_PAD_LEFT));
        $order = 1;

        foreach ($ordered as $installment) {
            if ($remaining === 0) {
                break;
            }
            $future = $installment->due_date->gt($date);
            if ($future && ! $loan->product->allows_advance_payment) {
                continue;
            }
            foreach ([LoanRepaymentComponent::Penalty, LoanRepaymentComponent::Fee, LoanRepaymentComponent::Interest, LoanRepaymentComponent::Principal] as $component) {
                $columnComponent = match ($component) {
                    LoanRepaymentComponent::Penalty => 'penalties',
                    LoanRepaymentComponent::Fee => 'fees',
                    default => $component->value,
                };
                $scheduled = (int) $installment->getAttribute('scheduled_'.$columnComponent.'_minor');
                $paid = (int) $installment->getAttribute('paid_'.$columnComponent.'_minor');
                $allocation = min($remaining, max(0, $scheduled - $paid));
                if ($allocation === 0) {
                    continue;
                }
                LoanRepaymentAllocation::query()->create(['organisation_id' => $loan->organisation_id, 'loan_repayment_id' => $repayment->getKey(), 'loan_repayment_installment_id' => $installment->getKey(), 'component' => $component, 'amount_minor' => $allocation, 'allocation_order' => $order++]);
                $installment->increment('paid_'.$columnComponent.'_minor', $allocation);
                $totals[$component->value] += $allocation;
                $remaining -= $allocation;
            }
            $installment->refresh();
            $paid = $installment->totalPaidMinor();
            $installment->update(['satisfaction_status' => $paid === 0 ? InstallmentSatisfactionStatus::Unsatisfied : ($paid >= $installment->totalScheduledMinor() ? InstallmentSatisfactionStatus::Satisfied : InstallmentSatisfactionStatus::PartiallySatisfied)]);
        }
        $totals['unallocated'] = $remaining;
        if ($remaining > 0 && ! $loan->product->allows_advance_payment) {
            throw new InvalidLoanOperation('The repayment exceeds allocatable loan amounts.');
        }

        return $totals;
    }
}
