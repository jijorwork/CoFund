<?php

namespace App\Domain\Welfare\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['public_id', 'organisation_id', 'welfare_claim_id', 'currency_code', 'benefit_calculation_type', 'benefit_rate_basis_points', 'benefit_base_minor', 'minimum_benefit_minor', 'maximum_benefit_minor', 'requested_benefit_minor', 'calculated_benefit_minor', 'approved_benefit_minor', 'waiting_period_end_date', 'claim_deadline_date', 'required_documents', 'required_approval_level', 'eligibility_snapshot'])]
class WelfareClaimTerm extends Model
{
    use HasUuids;

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new \LogicException('Welfare claim terms are immutable.'));
        static::deleting(fn (): never => throw new \LogicException('Welfare claim terms cannot be deleted.'));
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
        return ['waiting_period_end_date' => 'date', 'claim_deadline_date' => 'date', 'required_documents' => 'array', 'eligibility_snapshot' => 'array'];
    }
}
