<?php

namespace App\Domain\Expenses\Services;

use App\Domain\Expenses\Enums\ExpenseSequenceType;
use App\Domain\Expenses\Models\ExpenseNumberSequence;
use App\Domain\Organisations\Models\Organisation;
use Illuminate\Database\QueryException;

class ExpenseNumberGenerator
{
    public function next(Organisation $organisation, ExpenseSequenceType $type, int $year): string
    {
        try {
            ExpenseNumberSequence::query()->firstOrCreate(['organisation_id' => $organisation->getKey(), 'sequence_type' => $type->value, 'year' => $year]);
        } catch (QueryException) {
        }
        $sequence = ExpenseNumberSequence::query()->whereBelongsTo($organisation)->where('sequence_type', $type)->where('year', $year)->lockForUpdate()->firstOrFail();
        $sequence->increment('current_value');

        return sprintf('%s-%s-%d-%06d', strtoupper($organisation->code), $type->value, $year, $sequence->current_value);
    }
}
