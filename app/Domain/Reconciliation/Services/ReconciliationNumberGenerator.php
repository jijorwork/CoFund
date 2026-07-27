<?php

namespace App\Domain\Reconciliation\Services;

use App\Domain\Organisations\Models\Organisation;
use App\Domain\Reconciliation\Enums\ReconciliationSequenceType;
use App\Domain\Reconciliation\Models\ReconciliationNumberSequence;
use Illuminate\Database\QueryException;

class ReconciliationNumberGenerator
{
    public function next(Organisation $organisation, ReconciliationSequenceType $type, int $year): string
    {
        try {
            ReconciliationNumberSequence::query()->firstOrCreate([
                'organisation_id' => $organisation->getKey(),
                'sequence_type' => $type->value,
                'year' => $year,
            ]);
        } catch (QueryException) {
            // A concurrent request established the sequence.
        }

        $sequence = ReconciliationNumberSequence::query()
            ->whereBelongsTo($organisation)
            ->where('sequence_type', $type)
            ->where('year', $year)
            ->lockForUpdate()
            ->firstOrFail();
        $sequence->increment('current_value');

        return sprintf(
            '%s-%s-%d-%06d',
            strtoupper($organisation->code),
            $type->prefix(),
            $year,
            $sequence->current_value,
        );
    }
}
