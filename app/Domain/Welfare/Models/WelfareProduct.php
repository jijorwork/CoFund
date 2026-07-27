<?php

namespace App\Domain\Welfare\Models;

use App\Domain\Organisations\Models\Branch;
use App\Domain\Organisations\Models\Organisation;
use App\Domain\Welfare\Enums\WelfareProductStatus;
use Database\Factories\Domain\Welfare\Models\WelfareProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property WelfareProductStatus $status
 * @property array<string, mixed>|null $required_document_types
 */
#[Fillable(['public_id', 'organisation_id', 'branch_id', 'group_id', 'code', 'name', 'description', 'product_type', 'currency_code', 'eligibility_basis', 'minimum_membership_days', 'minimum_contribution_count', 'minimum_contribution_amount_minor', 'require_active_contribution_enrolment', 'require_good_standing', 'block_member_with_arrears', 'block_member_with_overdue_loan', 'waiting_period_count', 'waiting_period_unit', 'benefit_calculation_type', 'fixed_benefit_minor', 'benefit_percentage_basis_points', 'benefit_percentage_base', 'minimum_benefit_minor', 'maximum_benefit_minor', 'maximum_claims_per_period', 'claim_limit_period', 'maximum_total_benefit_per_period_minor', 'require_beneficiary', 'allow_member_as_recipient', 'allow_external_recipient', 'require_documents', 'require_verification', 'require_primary_approval', 'require_secondary_approval', 'secondary_approval_threshold_minor', 'partial_payment_allowed', 'payment_deadline_days', 'required_document_types', 'status', 'created_by_user_id', 'activated_at', 'suspended_at', 'closed_at'])]
class WelfareProduct extends Model
{
    /** @use HasFactory<WelfareProductFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $attributes = ['status' => 'draft', 'minimum_membership_days' => 0, 'minimum_contribution_count' => 0, 'minimum_contribution_amount_minor' => 0, 'waiting_period_count' => 0, 'require_primary_approval' => true, 'partial_payment_allowed' => true];

    protected static function booted(): void
    {
        static::updating(function (self $product): void {
            if ($product->claims()->whereNotNull('approved_at')->exists()) {
                $material = ['currency_code', 'benefit_calculation_type', 'fixed_benefit_minor', 'benefit_percentage_basis_points', 'benefit_percentage_base', 'minimum_benefit_minor', 'maximum_benefit_minor', 'waiting_period_count', 'waiting_period_unit'];
                if (array_intersect(array_keys($product->getDirty()), $material) !== []) {
                    throw new \LogicException('Material welfare benefit terms are immutable after approval.');
                }
            }
        });
    }

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    /** @return BelongsTo<Organisation,$this> */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    /** @return BelongsTo<Branch,$this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return HasMany<WelfareEnrolment,$this> */
    public function enrolments(): HasMany
    {
        return $this->hasMany(WelfareEnrolment::class);
    }

    /** @return HasMany<WelfareClaim,$this> */
    public function claims(): HasMany
    {
        return $this->hasMany(WelfareClaim::class);
    }

    /** @return array<string,string> */
    protected function casts(): array
    {
        return ['status' => WelfareProductStatus::class, 'require_active_contribution_enrolment' => 'boolean', 'require_good_standing' => 'boolean', 'block_member_with_arrears' => 'boolean', 'block_member_with_overdue_loan' => 'boolean', 'require_beneficiary' => 'boolean', 'allow_member_as_recipient' => 'boolean', 'allow_external_recipient' => 'boolean', 'require_documents' => 'boolean', 'require_verification' => 'boolean', 'require_primary_approval' => 'boolean', 'require_secondary_approval' => 'boolean', 'partial_payment_allowed' => 'boolean', 'required_document_types' => 'array', 'activated_at' => 'datetime', 'suspended_at' => 'datetime', 'closed_at' => 'datetime'];
    }
}
