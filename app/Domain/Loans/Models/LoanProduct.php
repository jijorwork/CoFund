<?php

namespace App\Domain\Loans\Models;

use App\Domain\Loans\Enums\InterestMethod;
use App\Domain\Loans\Enums\LoanProductStatus;
use App\Domain\Loans\Enums\ProcessingFeeTreatment;
use App\Domain\Loans\Enums\ProcessingFeeType;
use App\Domain\Loans\Enums\RepaymentFrequency;
use App\Domain\Loans\Enums\TermUnit;
use App\Domain\Organisations\Models\Branch;
use App\Domain\Organisations\Models\Organisation;
use Database\Factories\Domain\Loans\Models\LoanProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property LoanProductStatus $status
 * @property TermUnit $term_unit
 * @property RepaymentFrequency $repayment_frequency
 * @property InterestMethod $interest_method
 * @property ProcessingFeeType $processing_fee_type
 * @property ProcessingFeeTreatment $processing_fee_treatment
 * @property array<string,mixed>|null $late_fee_rules
 * @property array<string,mixed>|null $early_settlement_rules
 * @property bool $blocks_overdue_borrowers
 * @property bool $requires_collateral
 * @property bool $allows_partial_repayment
 * @property bool $allows_advance_payment
 */
#[Fillable(['public_id', 'organisation_id', 'branch_id', 'group_id', 'code', 'name', 'description', 'currency_code', 'minimum_amount_minor', 'maximum_amount_minor', 'minimum_term', 'maximum_term', 'term_unit', 'repayment_frequency', 'interest_method', 'interest_rate_basis_points', 'grace_period_days', 'minimum_membership_days', 'minimum_contribution_count', 'minimum_contribution_value_minor', 'maximum_loan_to_savings_basis_points', 'maximum_active_loans', 'blocks_overdue_borrowers', 'required_guarantor_count', 'guarantee_limit_basis_points', 'requires_collateral', 'processing_fee_type', 'processing_fee_fixed_minor', 'processing_fee_basis_points', 'processing_fee_treatment', 'late_fee_rules', 'early_settlement_rules', 'allows_partial_repayment', 'allows_advance_payment', 'secondary_approval_threshold_minor', 'non_performing_days', 'status', 'created_by_user_id', 'activated_at', 'closed_at'])]
class LoanProduct extends Model
{
    /** @use HasFactory<LoanProductFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $attributes = ['status' => 'draft', 'interest_rate_basis_points' => 0, 'grace_period_days' => 0, 'maximum_active_loans' => 1, 'required_guarantor_count' => 0, 'requires_collateral' => false, 'allows_partial_repayment' => true, 'allows_advance_payment' => false];

    protected static function booted(): void
    {
        static::updating(function (self $product): void {
            if ($product->applications()->whereHas('loan.disbursement')->exists()) {
                $material = ['currency_code', 'minimum_amount_minor', 'maximum_amount_minor', 'minimum_term', 'maximum_term', 'term_unit', 'repayment_frequency', 'interest_method', 'interest_rate_basis_points', 'processing_fee_type', 'processing_fee_fixed_minor', 'processing_fee_basis_points', 'processing_fee_treatment'];
                if (array_intersect(array_keys($product->getDirty()), $material) !== []) {
                    throw new \LogicException('Material product terms are immutable after disbursement.');
                }
            }
        });
    }

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    /** @return BelongsTo<Organisation, $this> */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return HasMany<LoanApplication, $this> */
    public function applications(): HasMany
    {
        return $this->hasMany(LoanApplication::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['status' => LoanProductStatus::class, 'term_unit' => TermUnit::class, 'repayment_frequency' => RepaymentFrequency::class, 'interest_method' => InterestMethod::class, 'processing_fee_type' => ProcessingFeeType::class, 'processing_fee_treatment' => ProcessingFeeTreatment::class, 'blocks_overdue_borrowers' => 'boolean', 'requires_collateral' => 'boolean', 'allows_partial_repayment' => 'boolean', 'allows_advance_payment' => 'boolean', 'late_fee_rules' => 'array', 'early_settlement_rules' => 'array', 'activated_at' => 'datetime', 'closed_at' => 'datetime'];
    }
}
