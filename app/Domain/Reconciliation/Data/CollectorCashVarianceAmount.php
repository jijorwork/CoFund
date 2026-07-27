<?php

namespace App\Domain\Reconciliation\Data;

final readonly class CollectorCashVarianceAmount
{
    public function __construct(
        public int $shortageMinor,
        public int $overageMinor,
    ) {}
}
