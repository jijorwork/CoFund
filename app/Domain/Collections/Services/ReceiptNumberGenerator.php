<?php

namespace App\Domain\Collections\Services;

use App\Domain\Collections\Models\ReceiptNumberSequence;
use App\Domain\Organisations\Models\Organisation;
use Illuminate\Database\QueryException;

class ReceiptNumberGenerator
{
    public function next(Organisation $organisation, int $year): string
    {
        try {
            ReceiptNumberSequence::query()->firstOrCreate([
                'organisation_id' => $organisation->getKey(),
                'year' => $year,
            ]);
        } catch (QueryException) {
            // A concurrent request established the sequence.
        }

        $sequence = ReceiptNumberSequence::query()
            ->whereBelongsTo($organisation)
            ->where('year', $year)
            ->lockForUpdate()
            ->firstOrFail();
        $sequence->increment('current_value');

        return sprintf(
            '%s-RCP-%d-%06d',
            strtoupper($organisation->code),
            $year,
            $sequence->current_value,
        );
    }
}
