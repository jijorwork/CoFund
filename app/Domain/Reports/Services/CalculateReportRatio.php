<?php

namespace App\Domain\Reports\Services;

class CalculateReportRatio
{
    /** @return array{numerator: int, denominator: int, ratio_basis_points: int|null} */
    public function calculate(int $numerator, int $denominator): array
    {
        return [
            'numerator' => $numerator,
            'denominator' => $denominator,
            'ratio_basis_points' => $denominator === 0
                ? null
                : intdiv($numerator * 10000, abs($denominator)),
        ];
    }
}
