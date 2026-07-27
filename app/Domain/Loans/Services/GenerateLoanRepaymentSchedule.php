<?php

namespace App\Domain\Loans\Services;

use App\Domain\Loans\Enums\InterestMethod;
use App\Domain\Loans\Enums\RepaymentFrequency;
use App\Domain\Loans\Models\Loan;
use App\Domain\Loans\Models\LoanRepaymentInstallment;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class GenerateLoanRepaymentSchedule
{
    /** @return list<LoanRepaymentInstallment> */
    public function generate(Loan $loan): array
    {
        return DB::transaction(function () use ($loan): array {
            $locked = Loan::query()->lockForUpdate()->whereKey($loan->getKey())->firstOrFail();
            if ($locked->installments()->exists()) {
                return array_values($locked->installments()->get()->all());
            }
            $term = $locked->offer->term;
            $principalTotal = $locked->principal_minor + $locked->financed_fee_minor;
            $principalPart = intdiv($principalTotal, $term);
            $interestPart = intdiv($locked->contractual_interest_minor, $term);
            $opening = $principalTotal;
            $periodStart = CarbonImmutable::parse($locked->disbursement_date ?? $locked->offer->issued_at->toDateString());
            $rows = [];

            for ($sequence = 1; $sequence <= $term; $sequence++) {
                $due = $this->advance($periodStart, $locked->repayment_frequency);
                $principal = $sequence === $term ? $opening : $principalPart;
                $interest = match ($locked->interest_method) {
                    InterestMethod::ZeroInterest => 0,
                    InterestMethod::Flat => $sequence === $term ? $locked->contractual_interest_minor - ($interestPart * ($term - 1)) : $interestPart,
                    InterestMethod::ReducingBalance => $sequence === $term
                        ? $locked->contractual_interest_minor - collect($rows)->sum('scheduled_interest_minor')
                        : intdiv($opening * $locked->interest_rate_basis_points + 9999, 10000),
                };
                $rows[] = LoanRepaymentInstallment::query()->create(['organisation_id' => $locked->organisation_id, 'loan_id' => $locked->getKey(), 'sequence_number' => $sequence, 'period_start' => $periodStart, 'period_end' => $due, 'due_date' => $due, 'grace_due_date' => $due->addDays($locked->offer->grace_period_days), 'opening_principal_minor' => $opening, 'scheduled_principal_minor' => $principal, 'scheduled_interest_minor' => $interest]);
                $opening -= $principal;
                $periodStart = $due;
            }

            return $rows;
        }, attempts: 3);
    }

    private function advance(CarbonImmutable $date, RepaymentFrequency $frequency): CarbonImmutable
    {
        return match ($frequency) {
            RepaymentFrequency::Weekly => $date->addWeek(),
            RepaymentFrequency::Fortnightly => $date->addWeeks(2),
            RepaymentFrequency::Monthly => $date->addMonthNoOverflow(),
        };
    }
}
