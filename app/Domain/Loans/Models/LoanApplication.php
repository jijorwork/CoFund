<?php

namespace App\Domain\Loans\Models;

use App\Domain\Loans\Enums\EligibilityStatus;
use App\Domain\Loans\Enums\LoanApplicationStatus;
use App\Domain\Loans\Enums\RepaymentFrequency;
use App\Domain\Membership\Models\Member;
use App\Domain\Organisations\Models\Branch;
use App\Domain\Organisations\Models\Organisation;
use Database\Factories\Domain\Loans\Models\LoanApplicationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property EligibilityStatus $eligibility_status
 * @property LoanApplicationStatus $workflow_status
 * @property RepaymentFrequency $repayment_frequency
 * @property array<string,mixed>|null $eligibility_snapshot
 * @property array<string,mixed>|null $product_terms_snapshot
 * @property Carbon $application_date
 * @property Carbon|null $submitted_at
 * @property Carbon|null $reviewed_at
 * @property Carbon|null $approved_at
 * @property Carbon|null $rejected_at
 * @property Carbon|null $cancelled_at
 */
#[Fillable(['public_id', 'organisation_id', 'branch_id', 'member_id', 'loan_product_id', 'application_number', 'requested_principal_minor', 'requested_term', 'repayment_frequency', 'purpose', 'purpose_category', 'income_source', 'declared_monthly_income_minor', 'declared_monthly_expenses_minor', 'existing_debt_minor', 'preferred_disbursement_method', 'application_date', 'eligibility_status', 'workflow_status', 'eligibility_snapshot', 'product_terms_snapshot', 'submitted_by_user_id', 'reviewed_by_user_id', 'approved_by_user_id', 'submitted_at', 'reviewed_at', 'approved_at', 'rejected_at', 'rejection_reason', 'cancelled_at', 'cancellation_reason'])]
class LoanApplication extends Model
{
    /** @use HasFactory<LoanApplicationFactory> */
    use HasFactory, HasUuids;

    protected $attributes = ['eligibility_status' => 'not_evaluated', 'workflow_status' => 'draft'];

    protected static function booted(): void
    {
        static::updating(function (self $application): void {
            if ($application->getOriginal('workflow_status') !== LoanApplicationStatus::Draft->value) {
                $workflow = ['eligibility_status', 'workflow_status', 'eligibility_snapshot', 'product_terms_snapshot', 'submitted_by_user_id', 'reviewed_by_user_id', 'approved_by_user_id', 'submitted_at', 'reviewed_at', 'approved_at', 'rejected_at', 'rejection_reason', 'cancelled_at', 'cancellation_reason', 'updated_at'];
                if (array_diff(array_keys($application->getDirty()), $workflow) !== []) {
                    throw new \LogicException('Only draft loan applications are editable.');
                }
            }
        });
        static::deleting(fn (): never => throw new \LogicException('Loan applications cannot be deleted.'));
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

    /** @return BelongsTo<Member, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /** @return BelongsTo<LoanProduct, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(LoanProduct::class, 'loan_product_id');
    }

    /** @return HasMany<LoanGuarantor, $this> */
    public function guarantors(): HasMany
    {
        return $this->hasMany(LoanGuarantor::class);
    }

    /** @return HasMany<LoanCollateral, $this> */
    public function collateral(): HasMany
    {
        return $this->hasMany(LoanCollateral::class);
    }

    /** @return HasMany<LoanApproval, $this> */
    public function approvals(): HasMany
    {
        return $this->hasMany(LoanApproval::class)->orderBy('sequence_number');
    }

    /** @return HasMany<LoanOffer, $this> */
    public function offers(): HasMany
    {
        return $this->hasMany(LoanOffer::class);
    }

    /** @return HasOne<Loan, $this> */
    public function loan(): HasOne
    {
        return $this->hasOne(Loan::class);
    }

    /** @return HasMany<LoanDocument, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(LoanDocument::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['eligibility_status' => EligibilityStatus::class, 'workflow_status' => LoanApplicationStatus::class, 'repayment_frequency' => RepaymentFrequency::class, 'application_date' => 'date', 'eligibility_snapshot' => 'array', 'product_terms_snapshot' => 'array', 'submitted_at' => 'datetime', 'reviewed_at' => 'datetime', 'approved_at' => 'datetime', 'rejected_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }
}
