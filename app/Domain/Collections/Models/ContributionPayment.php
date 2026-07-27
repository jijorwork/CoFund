<?php

namespace App\Domain\Collections\Models;

use App\Domain\Accounting\Models\FinancialTransaction;
use App\Domain\Collections\Enums\ContributionPaymentMethod;
use App\Domain\Collections\Enums\ContributionPaymentStatus;
use App\Domain\Contributions\Models\ContributionPlan;
use App\Domain\Contributions\Models\MemberPlanEnrolment;
use App\Domain\Membership\Models\Member;
use App\Domain\Organisations\Models\Branch;
use App\Domain\Organisations\Models\Organisation;
use App\Domain\Reconciliation\Models\CollectorCollectionSession;
use App\Domain\Wallets\Models\Wallet;
use App\Models\User;
use Database\Factories\Domain\Collections\Models\ContributionPaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property ContributionPaymentMethod $payment_method
 * @property ContributionPaymentStatus $status
 * @property Carbon $payment_date
 * @property Carbon $received_at
 */
#[Fillable([
    'public_id', 'organisation_id', 'branch_id', 'member_id', 'contribution_plan_id',
    'member_plan_enrolment_id', 'financial_transaction_id', 'receipt_id',
    'payment_number', 'payment_method', 'source_wallet_id', 'destination_wallet_id',
    'amount_minor', 'allocated_amount_minor', 'unallocated_amount_minor',
    'currency_code', 'payment_date', 'received_at', 'status', 'external_reference',
    'payer_name', 'payer_phone', 'collected_by_user_id', 'collector_wallet_id',
    'notes', 'idempotency_key', 'request_fingerprint', 'reversed_payment_id',
    'reversal_payment_id', 'reversal_reason', 'collector_collection_session_id',
])]
class ContributionPayment extends Model
{
    /** @use HasFactory<ContributionPaymentFactory> */
    use HasFactory, HasUuids;

    protected static function booted(): void
    {
        static::updating(function (self $payment): void {
            $allowed = ['status', 'receipt_id', 'reversal_payment_id', 'reversal_reason', 'updated_at'];

            if (array_diff(array_keys($payment->getDirty()), $allowed) !== []) {
                throw new \LogicException('Posted contribution payments are immutable.');
            }
        });
        static::deleting(fn (): never => throw new \LogicException('Contribution payments cannot be deleted.'));
    }

    protected static function newFactory(): ContributionPaymentFactory
    {
        return ContributionPaymentFactory::new();
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

    /** @return BelongsTo<ContributionPlan, $this> */
    public function contributionPlan(): BelongsTo
    {
        return $this->belongsTo(ContributionPlan::class);
    }

    /** @return BelongsTo<MemberPlanEnrolment, $this> */
    public function enrolment(): BelongsTo
    {
        return $this->belongsTo(MemberPlanEnrolment::class, 'member_plan_enrolment_id');
    }

    /** @return BelongsTo<FinancialTransaction, $this> */
    public function financialTransaction(): BelongsTo
    {
        return $this->belongsTo(FinancialTransaction::class);
    }

    /** @return BelongsTo<Wallet, $this> */
    public function sourceWallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'source_wallet_id');
    }

    /** @return BelongsTo<Wallet, $this> */
    public function destinationWallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'destination_wallet_id');
    }

    /** @return BelongsTo<Wallet, $this> */
    public function collectorWallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'collector_wallet_id');
    }

    /** @return BelongsTo<User, $this> */
    public function collectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collected_by_user_id');
    }

    /** @return BelongsTo<Receipt, $this> */
    public function receipt(): BelongsTo
    {
        return $this->belongsTo(Receipt::class);
    }

    /** @return HasMany<ContributionPaymentAllocation, $this> */
    public function allocations(): HasMany
    {
        return $this->hasMany(ContributionPaymentAllocation::class);
    }

    /** @return BelongsTo<ContributionPayment, $this> */
    public function reversedPayment(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversed_payment_id');
    }

    /** @return BelongsTo<ContributionPayment, $this> */
    public function reversalPayment(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_payment_id');
    }

    /** @return BelongsTo<CollectorCollectionSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(CollectorCollectionSession::class, 'collector_collection_session_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'payment_method' => ContributionPaymentMethod::class,
            'status' => ContributionPaymentStatus::class,
            'payment_date' => 'date',
            'received_at' => 'datetime',
        ];
    }
}
