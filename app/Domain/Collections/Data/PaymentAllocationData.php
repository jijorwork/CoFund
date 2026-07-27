<?php

namespace App\Domain\Collections\Data;

use App\Domain\Contributions\Models\ContributionObligation;

final readonly class PaymentAllocationData
{
    public function __construct(
        public ContributionObligation $obligation,
        public int $amountMinor,
    ) {}
}
