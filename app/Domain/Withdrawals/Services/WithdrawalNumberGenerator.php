<?php

namespace App\Domain\Withdrawals\Services;

use App\Domain\Organisations\Models\Organisation;
use App\Domain\Withdrawals\Enums\WithdrawalSequenceType;
use App\Domain\Withdrawals\Models\WithdrawalNumberSequence;

class WithdrawalNumberGenerator
{
    public function next(Organisation $organisation, WithdrawalSequenceType $type, int $year): string
    {
        WithdrawalNumberSequence::query()->firstOrCreate(['organisation_id' => $organisation->getKey(), 'sequence_type' => $type->value, 'sequence_year' => $year]);
        $sequence = WithdrawalNumberSequence::query()->where('organisation_id', $organisation->getKey())->where('sequence_type', $type->value)->where('sequence_year', $year)->lockForUpdate()->firstOrFail();
        $number = $sequence->next_number;
        $sequence->increment('next_number');

        return sprintf('%s-%s-%d-%06d', $organisation->code, $type->prefix(), $year, $number);
    }
}
