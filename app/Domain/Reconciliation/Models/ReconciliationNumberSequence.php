<?php

namespace App\Domain\Reconciliation\Models;

use App\Domain\Organisations\Models\Organisation;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['organisation_id', 'sequence_type', 'year', 'current_value'])]
class ReconciliationNumberSequence extends Model
{
    /** @return BelongsTo<Organisation, $this> */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }
}
