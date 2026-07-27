<?php

namespace App\Domain\Welfare\Models;

use Database\Factories\Domain\Welfare\Models\WelfareClaimBeneficiaryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Hidden(['identification_reference_encrypted'])]
#[Fillable(['public_id', 'organisation_id', 'welfare_claim_id', 'beneficiary_type', 'member_beneficiary_id', 'member_contact_id', 'name', 'relationship_to_member', 'phone', 'email', 'identification_type', 'identification_reference_encrypted', 'identification_last_four', 'allocation_basis_points', 'allocated_benefit_minor', 'is_primary', 'payment_eligible', 'status'])]
class WelfareClaimBeneficiary extends Model
{
    /** @use HasFactory<WelfareClaimBeneficiaryFactory> */
    use HasFactory, HasUuids;

    protected $attributes = ['status' => 'active', 'is_primary' => false, 'payment_eligible' => true];

    protected static function booted(): void
    {
        static::deleting(fn (): never => throw new \LogicException('Submitted welfare beneficiaries cannot be deleted.'));
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
        return ['identification_reference_encrypted' => 'encrypted', 'is_primary' => 'boolean', 'payment_eligible' => 'boolean'];
    }
}
