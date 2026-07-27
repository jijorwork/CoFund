<?php

namespace App\Domain\Loans\Services;

use App\Domain\Loans\Enums\InstallmentTimingStatus;
use App\Domain\Loans\Enums\LoanPerformanceStatus;
use App\Domain\Loans\Models\Loan;
use Carbon\CarbonImmutable;

class CalculateLoanArrears
{
    /** @return array{arrears_minor:int,overdue_installment_count:int,days_past_due:int,performance_status:LoanPerformanceStatus} */
    public function calculate(Loan $loan, CarbonImmutable $asOf): array
    {
        $installments = $loan->installments()->where('schedule_status', 'active')->get();
        $arrears = 0;
        $oldest = null;
        $overdue = 0;
        $inGrace = false;

        foreach ($installments as $installment) {
            $outstanding = max(0, $installment->totalScheduledMinor() - $installment->totalPaidMinor());
            if ($outstanding === 0) {
                continue;
            }
            if ($installment->grace_due_date->lt($asOf)) {
                $arrears += $outstanding;
                $overdue++;
                $oldest = $oldest === null || $installment->grace_due_date->lt($oldest) ? $installment->grace_due_date : $oldest;
                $installment->update(['timing_status' => InstallmentTimingStatus::Overdue]);
            } elseif ($installment->due_date->lte($asOf)) {
                $inGrace = true;
                $installment->update(['timing_status' => InstallmentTimingStatus::Due]);
            }
        }
        $days = $oldest === null ? 0 : (int) $oldest->diffInDays($asOf);
        $status = $arrears > 0 ? ($days >= $loan->product->non_performing_days ? LoanPerformanceStatus::NonPerforming : LoanPerformanceStatus::Overdue) : ($inGrace ? LoanPerformanceStatus::InGrace : LoanPerformanceStatus::Current);

        return ['arrears_minor' => $arrears, 'overdue_installment_count' => $overdue, 'days_past_due' => $days, 'performance_status' => $status];
    }
}
