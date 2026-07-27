<?php

namespace App\Domain\Loans\Services;

use App\Domain\Loans\Enums\LoanPerformanceStatus;
use App\Domain\Loans\Enums\LoanStatus;
use App\Domain\Loans\Models\Loan;
use Carbon\CarbonImmutable;

class RecalculateLoanPosition
{
    public function __construct(private CalculateLoanArrears $arrears) {}

    /** @return array<string,mixed> */
    public function recalculate(Loan $loan, CarbonImmutable $asOf): array
    {
        $components = ['principal', 'interest', 'fees', 'penalties'];
        $position = [];
        foreach ($components as $component) {
            $singular = match ($component) {
                'fees' => 'fee', 'penalties' => 'penalty', default => $component
            };
            $scheduled = (int) $loan->installments()->where('schedule_status', 'active')->sum('scheduled_'.$component.'_minor');
            $paid = (int) $loan->installments()->where('schedule_status', 'active')->sum('paid_'.$component.'_minor');
            $position['outstanding_'.$component.'_minor'] = max(0, $scheduled - $paid);
            $position[$singular.'_paid_minor'] = $paid;
        }
        $arrears = $this->arrears->calculate($loan, $asOf);
        $nextDue = $loan->installments()->where('schedule_status', 'active')->whereColumn('paid_principal_minor', '<', 'scheduled_principal_minor')->orderBy('due_date')->value('due_date');
        $totalOutstanding = array_sum([$position['outstanding_principal_minor'], $position['outstanding_interest_minor'], $position['outstanding_fees_minor'], $position['outstanding_penalties_minor']]);
        $position += $arrears;
        $position['total_paid_minor'] = $position['principal_paid_minor'] + $position['interest_paid_minor'] + $position['fee_paid_minor'] + $position['penalty_paid_minor'];
        $position['total_outstanding_minor'] = $totalOutstanding;
        $position['settlement_amount_minor'] = $totalOutstanding;
        $position['next_due_date'] = $nextDue;
        if ($totalOutstanding === 0 && $loan->status === LoanStatus::Active) {
            $loan->update(['status' => LoanStatus::Settled, 'performance_status' => LoanPerformanceStatus::Settled]);
        } else {
            $loan->update(['outstanding_principal_minor' => $position['outstanding_principal_minor'], 'outstanding_interest_minor' => $position['outstanding_interest_minor'], 'outstanding_fees_minor' => $position['outstanding_fees_minor'], 'outstanding_penalties_minor' => $position['outstanding_penalties_minor'], 'arrears_minor' => $arrears['arrears_minor'], 'days_past_due' => $arrears['days_past_due'], 'performance_status' => $arrears['performance_status'], 'next_due_date' => $nextDue]);
        }

        return $position;
    }
}
