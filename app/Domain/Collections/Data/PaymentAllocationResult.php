<?php

namespace App\Domain\Collections\Data;

final readonly class PaymentAllocationResult
{
    /** @param list<PaymentAllocationData> $allocations */
    public function __construct(
        public array $allocations,
        public int $allocatedAmountMinor,
        public int $unallocatedAmountMinor,
    ) {}
}
