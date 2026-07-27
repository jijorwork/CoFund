<?php

namespace App\Domain\Reconciliation\Services;

use App\Domain\Collections\Enums\ContributionPaymentMethod;
use App\Domain\Collections\Enums\ContributionPaymentStatus;
use App\Domain\Collections\Models\ContributionPayment;
use App\Domain\Reconciliation\Models\CollectorCollectionSession;

class CalculateCollectorSessionExpectedAmounts
{
    /**
     * @return array{
     *     total_posted_collector_cash_minor: int,
     *     total_reversed_collector_cash_minor: int,
     *     net_expected_cash_minor: int,
     *     active_payment_count: int,
     *     payment_method_breakdown: array<string, int>,
     *     member_count: int,
     *     receipt_count: int,
     *     earliest_payment_time: string|null,
     *     latest_payment_time: string|null
     * }
     */
    public function calculate(CollectorCollectionSession $session): array
    {
        $base = ContributionPayment::query()
            ->whereBelongsTo($session, 'session')
            ->where('organisation_id', $session->organisation_id)
            ->where('collector_wallet_id', $session->collector_wallet_id)
            ->where('payment_method', ContributionPaymentMethod::Cash);
        $active = (clone $base)
            ->whereNull('reversed_payment_id')
            ->whereNot('status', ContributionPaymentStatus::Reversed);
        $aggregate = $active->toBase()->selectRaw(
            'COALESCE(SUM(amount_minor), 0) as expected_cash,
             COUNT(*) as payment_count,
             COUNT(DISTINCT member_id) as member_count,
             SUM(CASE WHEN receipt_id IS NOT NULL THEN 1 ELSE 0 END) as receipt_count,
             MIN(received_at) as earliest_payment_time,
             MAX(received_at) as latest_payment_time',
        )->first();

        return [
            'total_posted_collector_cash_minor' => (int) ($aggregate->expected_cash ?? 0),
            'total_reversed_collector_cash_minor' => (int) (clone $base)
                ->where('status', ContributionPaymentStatus::Reversed)
                ->sum('amount_minor'),
            'net_expected_cash_minor' => (int) ($aggregate->expected_cash ?? 0),
            'active_payment_count' => (int) ($aggregate->payment_count ?? 0),
            'payment_method_breakdown' => [
                ContributionPaymentMethod::Cash->value => (int) ($aggregate->expected_cash ?? 0),
            ],
            'member_count' => (int) ($aggregate->member_count ?? 0),
            'receipt_count' => (int) ($aggregate->receipt_count ?? 0),
            'earliest_payment_time' => $aggregate->earliest_payment_time,
            'latest_payment_time' => $aggregate->latest_payment_time,
        ];
    }
}
