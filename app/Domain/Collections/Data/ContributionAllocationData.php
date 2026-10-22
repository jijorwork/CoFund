<?php

namespace App\Domain\Collections\Data;

use App\Domain\Collections\Enums\PaymentAllocationStrategy;
use App\Domain\Contributions\Models\ContributionPlan;
use App\Domain\Contributions\Models\MemberPlanEnrolment;

final readonly class ContributionAllocationData
{
    /** @param list<PaymentAllocationData> $selectedAllocations */
    public function __construct(
        public ?MemberPlanEnrolment $enrolment,
        public ?ContributionPlan $plan,
        public int $amountMinor,
        public PaymentAllocationStrategy $allocationStrategy,
        public array $selectedAllocations = [],
    ) {}

    public static function fromPayment(ContributionPaymentData $payment): self
    {
        return new self(
            enrolment: $payment->enrolment,
            plan: $payment->plan,
            amountMinor: $payment->amountMinor,
            allocationStrategy: $payment->allocationStrategy,
            selectedAllocations: $payment->selectedAllocations,
        );
    }
}
