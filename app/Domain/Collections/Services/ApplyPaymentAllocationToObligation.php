<?php

namespace App\Domain\Collections\Services;

use App\Domain\Collections\Enums\PaymentAllocationStatus;
use App\Domain\Collections\Exceptions\InvalidContributionPayment;
use App\Domain\Collections\Models\ContributionPaymentAllocation;
use App\Domain\Contributions\Enums\ObligationSatisfactionStatus;
use App\Domain\Contributions\Models\ContributionObligation;

class ApplyPaymentAllocationToObligation
{
    public function recalculate(ContributionObligation $obligation): ContributionObligation
    {
        $locked = ContributionObligation::query()
            ->whereKey($obligation->getKey())
            ->lockForUpdate()
            ->firstOrFail();
        $total = (int) ContributionPaymentAllocation::query()
            ->whereBelongsTo($locked, 'obligation')
            ->where('status', PaymentAllocationStatus::Active)
            ->sum('amount_minor');
        $expected = (int) $locked->expected_amount_minor;

        if ($total > $expected) {
            throw new InvalidContributionPayment('An obligation cannot be over-satisfied.');
        }

        $status = match (true) {
            $total === 0 => ObligationSatisfactionStatus::Unsatisfied,
            $total === $expected => ObligationSatisfactionStatus::Satisfied,
            default => ObligationSatisfactionStatus::PartiallySatisfied,
        };

        $locked->update([
            'satisfied_amount_minor' => $total,
            'outstanding_amount_minor' => $expected - $total,
            'satisfaction_status' => $status,
            'satisfied_at' => $status === ObligationSatisfactionStatus::Satisfied ? now() : null,
        ]);

        return $locked->refresh();
    }
}
