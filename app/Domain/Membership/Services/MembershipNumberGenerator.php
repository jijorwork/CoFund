<?php

namespace App\Domain\Membership\Services;

use App\Domain\Membership\Models\MembershipNumberSequence;
use App\Domain\Organisations\Models\Organisation;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MembershipNumberGenerator
{
    public function next(Organisation $organisation, int $year): string
    {
        return DB::transaction(function () use ($organisation, $year): string {
            $sequence = $this->lockedSequence($organisation, $year);
            $sequence->increment('current_value');
            $sequence->refresh();

            return Str::upper($organisation->code)
                .'-'.$year
                .'-'.str_pad((string) $sequence->current_value, 6, '0', STR_PAD_LEFT);
        });
    }

    private function lockedSequence(Organisation $organisation, int $year): MembershipNumberSequence
    {
        try {
            MembershipNumberSequence::query()->firstOrCreate([
                'organisation_id' => $organisation->getKey(),
                'sequence_year' => $year,
            ], ['current_value' => 0]);
        } catch (QueryException) {
            // A concurrent request created the unique sequence row first.
        }

        return MembershipNumberSequence::query()
            ->whereBelongsTo($organisation)
            ->where('sequence_year', $year)
            ->lockForUpdate()
            ->firstOrFail();
    }
}
