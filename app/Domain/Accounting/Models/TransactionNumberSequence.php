<?php

namespace App\Domain\Accounting\Models;

use App\Domain\Organisations\Models\Organisation;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['organisation_id', 'sequence_year', 'current_value'])]
class TransactionNumberSequence extends Model
{
    /** @return BelongsTo<Organisation, $this> */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }
}
