<?php

namespace App\Domain\Collections\Models;

use App\Domain\Organisations\Models\Organisation;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['organisation_id', 'year', 'current_value'])]
class ReceiptNumberSequence extends Model
{
    /** @return BelongsTo<Organisation, $this> */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }
}
