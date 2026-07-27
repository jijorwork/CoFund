<?php

namespace App\Domain\Loans\Models;

use App\Domain\Loans\Enums\InterestMethod;
use App\Domain\Loans\Enums\LoanOfferStatus;
use App\Domain\Loans\Enums\RepaymentFrequency;
use App\Domain\Organisations\Models\Organisation;
use Database\Factories\Domain\Loans\Models\LoanOfferFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property LoanOfferStatus $status
 * @property RepaymentFrequency $repayment_frequency
 * @property InterestMethod $interest_method
 * @property Carbon $first_repayment_date
 * @property Carbon $maturity_date
 * @property Carbon $issued_at
 * @property Carbon $expires_at
 * @property Carbon|null $accepted_at
 */
#[Fillable(['public_id', 'organisation_id', 'loan_application_id', 'offer_number', 'version', 'principal_minor', 'interest_minor', 'fees_minor', 'financed_fee_minor', 'deducted_fee_minor', 'total_repayable_minor', 'term', 'repayment_frequency', 'interest_method', 'interest_rate_basis_points', 'grace_period_days', 'first_repayment_date', 'maturity_date', 'issued_at', 'expires_at', 'status', 'issued_by_user_id', 'accepted_by_user_id', 'accepted_at', 'declined_at', 'decline_reason'])]
class LoanOffer extends Model
{
    /** @use HasFactory<LoanOfferFactory> */
    use HasFactory, HasUuids;

    protected $attributes = ['status' => 'issued'];

    protected static function booted(): void
    {
        static::updating(function (self $offer): void {
            if (array_diff(array_keys($offer->getDirty()), ['status', 'accepted_by_user_id', 'accepted_at', 'declined_at', 'decline_reason', 'updated_at']) !== []) {
                throw new \LogicException('Issued loan terms are immutable.');
            }
        });
        static::deleting(fn (): never => throw new \LogicException('Loan offers cannot be deleted.'));
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

    /** @return BelongsTo<Organisation, $this> */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['status' => LoanOfferStatus::class, 'repayment_frequency' => RepaymentFrequency::class, 'interest_method' => InterestMethod::class, 'first_repayment_date' => 'date', 'maturity_date' => 'date', 'issued_at' => 'datetime', 'expires_at' => 'datetime', 'accepted_at' => 'datetime', 'declined_at' => 'datetime'];
    }
}
