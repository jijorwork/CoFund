<?php

namespace App\Domain\Loans\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon $requested_at
 * @property Carbon $decided_at
 */
#[Fillable(['public_id', 'organisation_id', 'loan_application_id', 'approval_level', 'decision', 'requested_at', 'decided_at', 'decided_by_user_id', 'acting_role_id', 'approved_principal_minor', 'approved_term', 'recommended_rate_basis_points', 'reason', 'override_type', 'sequence_number'])]
class LoanApproval extends Model
{
    use HasUuids;

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new \LogicException('Loan approvals are append-only.'));
        static::deleting(fn (): never => throw new \LogicException('Loan approvals are append-only.'));
    }

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
        return ['requested_at' => 'datetime', 'decided_at' => 'datetime'];
    }
}
