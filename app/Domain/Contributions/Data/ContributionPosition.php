<?php

namespace App\Domain\Contributions\Data;

use App\Domain\Contributions\Enums\ContributionPositionStatus;

final readonly class ContributionPosition
{
    public function __construct(
        public int $expectedToDateMinor,
        public int $satisfiedToDateMinor,
        public int $outstandingToDateMinor,
        public int $overdueObligationCount,
        public int $upcomingObligationCount,
        public ?string $nextDueDate,
        public ?int $nextExpectedAmountMinor,
        public ContributionPositionStatus $status,
    ) {}

    /** @return array<string, int|string|null> */
    public function toArray(): array
    {
        return [
            'expected_to_date_minor' => $this->expectedToDateMinor,
            'satisfied_to_date_minor' => $this->satisfiedToDateMinor,
            'outstanding_to_date_minor' => $this->outstandingToDateMinor,
            'overdue_obligation_count' => $this->overdueObligationCount,
            'upcoming_obligation_count' => $this->upcomingObligationCount,
            'next_due_date' => $this->nextDueDate,
            'next_expected_amount_minor' => $this->nextExpectedAmountMinor,
            'position_status' => $this->status->value,
        ];
    }
}
