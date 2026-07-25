<?php

namespace App\Domain\Membership\Models;

use App\Domain\Organisations\Models\Organisation;
use Database\Factories\Domain\Membership\Models\MembershipNumberSequenceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['organisation_id', 'sequence_year', 'current_value'])]
class MembershipNumberSequence extends Model
{
    /** @use HasFactory<MembershipNumberSequenceFactory> */
    use HasFactory;

    protected static function newFactory(): MembershipNumberSequenceFactory
    {
        return MembershipNumberSequenceFactory::new();
    }

    /** @return BelongsTo<Organisation, $this> */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }
}
