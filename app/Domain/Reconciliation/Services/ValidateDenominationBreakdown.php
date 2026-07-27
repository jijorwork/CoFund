<?php

namespace App\Domain\Reconciliation\Services;

use App\Domain\Reconciliation\Exceptions\InvalidReconciliationOperation;

class ValidateDenominationBreakdown
{
    private const ALLOWED = [
        20000, 10000, 5000, 2000, 1000, 500, 200, 100,
        50, 20, 10, 5, 1,
    ];

    /**
     * @param  list<array{denomination_minor: int, quantity: int}>|null  $breakdown
     * @return list<array{denomination_minor: int, quantity: int}>|null
     */
    public function validate(?array $breakdown, int $declaredMinor): ?array
    {
        if ($breakdown === null) {
            return null;
        }

        $seen = [];
        $total = 0;

        foreach ($breakdown as $item) {
            $denomination = $item['denomination_minor'];
            $quantity = $item['quantity'];

            if (
                ! in_array($denomination, self::ALLOWED, true)
                || $quantity < 0
                || isset($seen[$denomination])
            ) {
                throw new InvalidReconciliationOperation('The denomination breakdown is invalid.');
            }

            $seen[$denomination] = true;
            $total += $denomination * $quantity;
        }

        if ($total !== $declaredMinor) {
            throw new InvalidReconciliationOperation('The denomination total must equal declared cash.');
        }

        usort($breakdown, fn (array $left, array $right): int => $right['denomination_minor'] <=> $left['denomination_minor']);

        return $breakdown;
    }
}
