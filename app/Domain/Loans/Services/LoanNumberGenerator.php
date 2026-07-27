<?php

namespace App\Domain\Loans\Services;

use App\Domain\Loans\Enums\LoanSequenceType;
use App\Domain\Loans\Models\LoanNumberSequence;
use App\Domain\Organisations\Models\Organisation;

class LoanNumberGenerator
{
    public function next(Organisation $organisation, LoanSequenceType $type, int $year): string
    {
        $sequence = LoanNumberSequence::query()->firstOrCreate(
            ['organisation_id' => $organisation->getKey(), 'sequence_type' => $type->value, 'sequence_year' => $year],
            ['next_number' => 1],
        );
        $locked = LoanNumberSequence::query()->lockForUpdate()->whereKey($sequence->getKey())->firstOrFail();
        $number = $locked->next_number;
        $locked->increment('next_number');

        return sprintf('%s-%s-%d-%06d', $organisation->code, $type->prefix(), $year, $number);
    }
}
