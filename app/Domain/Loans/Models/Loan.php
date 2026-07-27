<?php

namespace App\Domain\Loans\Models;

use App\Domain\Loans\Enums\InterestMethod;
use App\Domain\Loans\Enums\LoanPerformanceStatus;
use App\Domain\Loans\Enums\LoanStatus;
use App\Domain\Loans\Enums\RepaymentFrequency;
use App\Domain\Membership\Models\Member;
use Database\Factories\Domain\Loans\Models\LoanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property LoanStatus $status
 * @property LoanPerformanceStatus $performance_status
 * @property RepaymentFrequency $repayment_frequency
 * @property InterestMethod $interest_method
 * @property Carbon|null $disbursement_date
 * @property Carbon $first_repayment_date
 * @property Carbon $maturity_date
 * @property Carbon|null $next_due_date
 */
#[Fillable(['public_id', 'organisation_id', 'branch_id', 'member_id', 'loan_product_id', 'loan_application_id', 'loan_offer_id', 'loan_number', 'principal_minor', 'financed_fee_minor', 'contractual_interest_minor', 'total_repayable_minor', 'disbursed_amount_minor', 'currency_code', 'disbursement_date', 'first_repayment_date', 'maturity_date', 'repayment_frequency', 'interest_method', 'interest_rate_basis_points', 'status', 'performance_status', 'outstanding_principal_minor', 'outstanding_interest_minor', 'outstanding_fees_minor', 'outstanding_penalties_minor', 'arrears_minor', 'days_past_due', 'next_due_date'])]
class Loan extends Model
{
    /** @use HasFactory<LoanFactory> */
    use HasFactory, HasUuids;

    protected $attributes = ['status' => 'pending_disbursement', 'performance_status' => 'not_disbursed'];

    protected static function booted(): void
    {
        static::deleting(fn (): never => throw new \LogicException('Loans cannot be deleted.'));
    }

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
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

    /** @return BelongsTo<LoanApplication, $this> */
    public function application(): BelongsTo
    {
        return $this->belongsTo(LoanApplication::class, 'loan_application_id');
    }

    /** @return BelongsTo<LoanOffer, $this> */
    public function offer(): BelongsTo
    {
        return $this->belongsTo(LoanOffer::class, 'loan_offer_id');
    }

    /** @return HasMany<LoanRepaymentInstallment, $this> */
    public function installments(): HasMany
    {
        return $this->hasMany(LoanRepaymentInstallment::class)->orderBy('sequence_number');
    }

    /** @return HasMany<LoanRepayment, $this> */
    public function repayments(): HasMany
    {
        return $this->hasMany(LoanRepayment::class);
    }

    /** @return HasOne<LoanDisbursement, $this> */
    public function disbursement(): HasOne
    {
        return $this->hasOne(LoanDisbursement::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['status' => LoanStatus::class, 'performance_status' => LoanPerformanceStatus::class, 'repayment_frequency' => RepaymentFrequency::class, 'interest_method' => InterestMethod::class, 'disbursement_date' => 'date', 'first_repayment_date' => 'date', 'maturity_date' => 'date', 'next_due_date' => 'date'];
    }
}
