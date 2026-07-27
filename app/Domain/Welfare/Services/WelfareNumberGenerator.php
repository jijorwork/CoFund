<?php

namespace App\Domain\Welfare\Services;

use App\Domain\Organisations\Models\Organisation;
use App\Domain\Welfare\Enums\WelfareSequenceType;
use App\Domain\Welfare\Models\WelfareNumberSequence;
use Illuminate\Database\QueryException;

class WelfareNumberGenerator
{
    public function next(Organisation $organisation, WelfareSequenceType $type, int $year): string
    {
        try {
            WelfareNumberSequence::query()->firstOrCreate(['organisation_id' => $organisation->getKey(), 'sequence_type' => $type, 'sequence_year' => $year]);
        } catch (QueryException) {
        }
        $sequence = WelfareNumberSequence::query()->where('organisation_id', $organisation->getKey())->where('sequence_type', $type)->where('sequence_year', $year)->lockForUpdate()->firstOrFail();
        $number = $sequence->next_number;
        $sequence->increment('next_number');

        return sprintf('%s-%s-%d-%06d', strtoupper($organisation->code), $type->prefix(), $year, $number);
    }
}
