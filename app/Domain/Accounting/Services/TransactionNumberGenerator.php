<?php

namespace App\Domain\Accounting\Services;

use App\Domain\Accounting\Models\TransactionNumberSequence;
use App\Domain\Organisations\Models\Organisation;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TransactionNumberGenerator
{
    public function next(Organisation $organisation, int $year): string
    {
        return DB::transaction(function () use ($organisation, $year): string {
            $sequence = $this->lockedSequence($organisation, $year);
            $sequence->increment('current_value');
            $sequence->refresh();

            return Str::upper($organisation->code)
                .'-TXN-'.$year
                .'-'.str_pad((string) $sequence->current_value, 6, '0', STR_PAD_LEFT);
        });
    }

    private function lockedSequence(Organisation $organisation, int $year): TransactionNumberSequence
    {
        try {
            TransactionNumberSequence::query()->firstOrCreate([
                'organisation_id' => $organisation->getKey(),
                'sequence_year' => $year,
            ], ['current_value' => 0]);
        } catch (QueryException) {
            // A concurrent request created the unique sequence row first.
        }

        return TransactionNumberSequence::query()
            ->whereBelongsTo($organisation)
            ->where('sequence_year', $year)
            ->lockForUpdate()
            ->firstOrFail();
    }
}
