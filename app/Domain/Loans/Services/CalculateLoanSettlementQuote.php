<?php

namespace App\Domain\Loans\Services;

use App\Domain\Loans\Models\Loan;
use Carbon\CarbonImmutable;

class CalculateLoanSettlementQuote
{
    public function __construct(private RecalculateLoanPosition $positions) {}

    /** @return array{outstanding_principal_minor:int,outstanding_interest_minor:int,outstanding_fees_minor:int,outstanding_penalties_minor:int,settlement_discount_minor:int,settlement_fee_minor:int,total_settlement_minor:int,expires_on:string} */
    public function calculate(Loan $loan, CarbonImmutable $date): array
    {
        $position = $this->positions->recalculate($loan, $date);
        $rules = $loan->product->early_settlement_rules ?? [];
        $discount = (bool) ($rules['rebate_future_flat_interest'] ?? false)
            ? (int) $loan->installments()->where('due_date', '>', $date->toDateString())->sum('scheduled_interest_minor')
            : 0;
        $fee = (int) ($rules['settlement_fee_minor'] ?? 0);
        $total = max(0, (int) $position['total_outstanding_minor'] - $discount + $fee);

        return ['outstanding_principal_minor' => (int) $position['outstanding_principal_minor'], 'outstanding_interest_minor' => (int) $position['outstanding_interest_minor'], 'outstanding_fees_minor' => (int) $position['outstanding_fees_minor'], 'outstanding_penalties_minor' => (int) $position['outstanding_penalties_minor'], 'settlement_discount_minor' => $discount, 'settlement_fee_minor' => $fee, 'total_settlement_minor' => $total, 'expires_on' => $date->addDays(7)->toDateString()];
    }
}
