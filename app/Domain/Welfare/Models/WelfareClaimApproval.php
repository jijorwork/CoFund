<?php

namespace App\Domain\Welfare\Models;

use Database\Factories\Domain\Welfare\Models\WelfareClaimApprovalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['public_id', 'organisation_id', 'welfare_claim_id', 'approval_level', 'decision', 'requested_at', 'decided_at', 'decided_by_user_id', 'acting_role_id', 'recommended_benefit_minor', 'approved_benefit_minor', 'reason', 'override_type', 'sequence_number'])]
class WelfareClaimApproval extends Model
{
    /** @use HasFactory<WelfareClaimApprovalFactory> */
    use HasFactory, HasUuids;

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new \LogicException('Welfare approval history is append-only.'));
        static::deleting(fn (): never => throw new \LogicException('Welfare approvals cannot be deleted.'));
    }

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    /** @return BelongsTo<WelfareClaim,$this> */
    public function claim(): BelongsTo
    {
        return $this->belongsTo(WelfareClaim::class, 'welfare_claim_id');
    }

    /** @return array<string,string> */
    protected function casts(): array
    {
        return ['requested_at' => 'datetime', 'decided_at' => 'datetime'];
    }
}
