<?php

namespace App\Domain\Collections\Services;

use App\Domain\Collections\Models\PaymentNumberSequence;
use App\Domain\Organisations\Models\Organisation;
use Illuminate\Database\QueryException;

class PaymentNumberGenerator
{
    public function next(Organisation $organisation, int $year): string
    {
        try {
            PaymentNumberSequence::query()->firstOrCreate([
                'organisation_id' => $organisation->getKey(),
                'year' => $year,
            ]);
        } catch (QueryException) {
            // A concurrent request established the sequence.
        }

        $sequence = PaymentNumberSequence::query()
            ->whereBelongsTo($organisation)
            ->where('year', $year)
            ->lockForUpdate()
            ->firstOrFail();
        $sequence->increment('current_value');

        return sprintf(
            '%s-PAY-%d-%06d',
            strtoupper($organisation->code),
            $year,
            $sequence->current_value,
        );
    }
}
