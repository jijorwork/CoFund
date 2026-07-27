<?php

namespace App\Domain\Welfare\Models;

use App\Domain\Accounting\Models\FinancialTransaction;
use App\Domain\Membership\Models\Member;
use App\Domain\Welfare\Enums\WelfareApprovalStatus;
use App\Domain\Welfare\Enums\WelfareClaimStatus;
use App\Domain\Welfare\Enums\WelfareEligibilityStatus;
use App\Domain\Welfare\Enums\WelfarePaymentStatus;
use App\Domain\Welfare\Enums\WelfareVerificationStatus;
use Database\Factories\Domain\Welfare\Models\WelfareClaimFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property WelfareClaimStatus $status
 * @property WelfareEligibilityStatus $eligibility_status
 * @property WelfareVerificationStatus $verification_status
 * @property WelfareApprovalStatus $approval_status
 * @property WelfarePaymentStatus $payment_status
 * @property Carbon $claim_date
 * @property Carbon|null $submitted_at
 * @property Carbon|null $approved_at
 * @property Carbon|null $settled_at
 */
#[Fillable(['public_id', 'organisation_id', 'branch_id', 'group_id', 'claim_number', 'member_id', 'welfare_product_id', 'welfare_enrolment_id', 'welfare_event_id', 'welfare_event_type_id', 'claimant_type', 'claimant_member_id', 'claimant_name', 'claimant_contact', 'requested_benefit_minor', 'eligible_benefit_minor', 'approved_benefit_minor', 'currency_code', 'claim_date', 'submission_date', 'status', 'eligibility_status', 'verification_status', 'approval_status', 'payment_status', 'eligibility_snapshot', 'benefit_snapshot', 'submitted_at', 'submitted_by_user_id', 'reviewed_at', 'reviewed_by_user_id', 'verified_at', 'verified_by_user_id', 'approved_at', 'approved_by_user_id', 'rejected_at', 'rejected_by_user_id', 'rejection_reason', 'cancelled_at', 'cancelled_by_user_id', 'cancellation_reason', 'liability_recognised_at', 'liability_transaction_id', 'total_paid_minor', 'outstanding_payable_minor', 'settled_at', 'closed_at', 'created_by_user_id'])]
class WelfareClaim extends Model
{
    /** @use HasFactory<WelfareClaimFactory> */
    use HasFactory, HasUuids;

    protected $attributes = ['status' => 'draft', 'eligibility_status' => 'not_evaluated', 'verification_status' => 'pending', 'approval_status' => 'pending', 'payment_status' => 'not_payable', 'total_paid_minor' => 0, 'outstanding_payable_minor' => 0];

    protected static function booted(): void
    {
        static::deleting(fn (): never => throw new \LogicException('Welfare claims cannot be deleted.'));
    }

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    /** @return BelongsTo<Member,$this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /** @return BelongsTo<WelfareProduct,$this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(WelfareProduct::class, 'welfare_product_id');
    }

    /** @return BelongsTo<WelfareEnrolment,$this> */
    public function enrolment(): BelongsTo
    {
        return $this->belongsTo(WelfareEnrolment::class, 'welfare_enrolment_id');
    }

    /** @return BelongsTo<WelfareEvent,$this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(WelfareEvent::class, 'welfare_event_id');
    }

    /** @return BelongsTo<WelfareEventType,$this> */
    public function eventType(): BelongsTo
    {
        return $this->belongsTo(WelfareEventType::class, 'welfare_event_type_id');
    }

    /** @return HasOne<WelfareClaimTerm,$this> */
    public function terms(): HasOne
    {
        return $this->hasOne(WelfareClaimTerm::class);
    }

    /** @return HasMany<WelfareClaimBeneficiary,$this> */
    public function beneficiaries(): HasMany
    {
        return $this->hasMany(WelfareClaimBeneficiary::class);
    }

    /** @return HasMany<WelfareClaimApproval,$this> */
    public function approvals(): HasMany
    {
        return $this->hasMany(WelfareClaimApproval::class);
    }

    /** @return HasMany<WelfareDocument,$this> */
    public function documents(): HasMany
    {
        return $this->hasMany(WelfareDocument::class);
    }

    /** @return HasMany<WelfareClaimLiability,$this> */
    public function liabilities(): HasMany
    {
        return $this->hasMany(WelfareClaimLiability::class);
    }

    /** @return HasMany<WelfareBenefitPayment,$this> */
    public function payments(): HasMany
    {
        return $this->hasMany(WelfareBenefitPayment::class);
    }

    /** @return BelongsTo<FinancialTransaction,$this> */
    public function liabilityTransaction(): BelongsTo
    {
        return $this->belongsTo(FinancialTransaction::class, 'liability_transaction_id');
    }

    /** @return array<string,string> */
    protected function casts(): array
    {
        return ['status' => WelfareClaimStatus::class, 'eligibility_status' => WelfareEligibilityStatus::class, 'verification_status' => WelfareVerificationStatus::class, 'approval_status' => WelfareApprovalStatus::class, 'payment_status' => WelfarePaymentStatus::class, 'claim_date' => 'date', 'submission_date' => 'date', 'eligibility_snapshot' => 'array', 'benefit_snapshot' => 'array', 'submitted_at' => 'datetime', 'reviewed_at' => 'datetime', 'verified_at' => 'datetime', 'approved_at' => 'datetime', 'rejected_at' => 'datetime', 'cancelled_at' => 'datetime', 'liability_recognised_at' => 'datetime', 'settled_at' => 'datetime', 'closed_at' => 'datetime'];
    }
}
