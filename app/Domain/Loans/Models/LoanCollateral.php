<?php

namespace App\Domain\Loans\Models;

use App\Domain\Loans\Enums\CollateralStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property CollateralStatus $status
 * @property Carbon|null $valuation_date
 */
#[Fillable(['public_id', 'organisation_id', 'loan_application_id', 'collateral_type', 'description', 'owner_name', 'estimated_value_minor', 'accepted_value_minor', 'currency_code', 'identifier_encrypted', 'identifier_last_four', 'location', 'valuation_date', 'valuation_notes', 'status', 'reviewed_by_user_id', 'reviewed_at', 'released_at', 'release_reason'])]
class LoanCollateral extends Model
{
    use HasUuids;

    protected $hidden = ['identifier_encrypted'];

    protected $attributes = ['status' => 'proposed'];

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    /** @return BelongsTo<LoanApplication, $this> */
    public function application(): BelongsTo
    {
        return $this->belongsTo(LoanApplication::class, 'loan_application_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['status' => CollateralStatus::class, 'identifier_encrypted' => 'encrypted', 'valuation_date' => 'date', 'reviewed_at' => 'datetime', 'released_at' => 'datetime'];
    }
}
