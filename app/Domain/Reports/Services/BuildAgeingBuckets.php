<?php

namespace App\Domain\Reports\Services;

use Carbon\CarbonImmutable;

class BuildAgeingBuckets
{
    /**
     * @param  iterable<array{due_date: string, amount_minor: int}>  $items
     * @return array<string, array{count: int, amount_minor: int}>
     */
    public function build(iterable $items, CarbonImmutable $asOf): array
    {
        $buckets = [
            'current' => ['count' => 0, 'amount_minor' => 0],
            '1_30_days' => ['count' => 0, 'amount_minor' => 0],
            '31_60_days' => ['count' => 0, 'amount_minor' => 0],
            '61_90_days' => ['count' => 0, 'amount_minor' => 0],
            'over_90_days' => ['count' => 0, 'amount_minor' => 0],
        ];

        foreach ($items as $item) {
            $days = CarbonImmutable::parse($item['due_date'], $asOf->timezone)->startOfDay()->diffInDays($asOf->startOfDay(), false);
            $key = match (true) {
                $days <= 0 => 'current',
                $days <= 30 => '1_30_days',
                $days <= 60 => '31_60_days',
                $days <= 90 => '61_90_days',
                default => 'over_90_days',
            };
            $buckets[$key]['count']++;
            $buckets[$key]['amount_minor'] += $item['amount_minor'];
        }

        return $buckets;
    }
}
