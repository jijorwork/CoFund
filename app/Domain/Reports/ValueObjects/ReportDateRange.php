<?php

namespace App\Domain\Reports\ValueObjects;

use App\Domain\Organisations\Models\Organisation;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

final readonly class ReportDateRange
{
    public function __construct(
        public CarbonImmutable $localStart,
        public CarbonImmutable $localEnd,
        public CarbonImmutable $utcStart,
        public CarbonImmutable $utcEnd,
        public string $timezone,
    ) {}

    public static function from(
        Organisation $organisation,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?string $asOf = null,
        int $maximumDays = 366,
    ): self {
        $timezone = (string) ($organisation->timezone ?: config('cofund.organisations.default_timezone', 'Africa/Accra'));
        $today = CarbonImmutable::today($timezone);
        $end = CarbonImmutable::parse($asOf ?? $dateTo ?? $today->toDateString(), $timezone)->endOfDay();
        $start = CarbonImmutable::parse($dateFrom ?? $end->startOfMonth()->toDateString(), $timezone)->startOfDay();

        if ($end->isAfter($today->endOfDay())) {
            throw new InvalidArgumentException('Future report dates are not supported.');
        }
        if ($start->isAfter($end)) {
            throw new InvalidArgumentException('The report start date must not be after the end date.');
        }
        if ($start->diffInDays($end) + 1 > $maximumDays) {
            throw new InvalidArgumentException("Synchronous reports are limited to {$maximumDays} days.");
        }

        return new self(
            localStart: $start,
            localEnd: $end,
            utcStart: $start->utc(),
            utcEnd: $end->utc(),
            timezone: $timezone,
        );
    }

    /** @return array<string, string> */
    public function metadata(): array
    {
        return [
            'date_from' => $this->localStart->toDateString(),
            'date_to' => $this->localEnd->toDateString(),
            'timezone' => $this->timezone,
            'as_of' => $this->localEnd->toIso8601String(),
        ];
    }
}
