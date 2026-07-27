<?php

namespace App\Domain\Withdrawals\Models;

use App\Domain\Membership\Models\Member;
use App\Domain\Organisations\Models\Branch;
use App\Domain\Wallets\Models\Wallet;
use App\Domain\Withdrawals\Enums\WithdrawalRequestStatus;
use Database\Factories\Domain\Withdrawals\Models\WithdrawalRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property WithdrawalRequestStatus $status
 * @property Carbon $request_date
 */
#[Fillable(['public_id', 'organisation_id', 'branch_id', 'group_id', 'request_number', 'member_id', 'withdrawal_product_id', 'withdrawal_reason_id', 'savings_wallet_id', 'requested_amount_minor', 'eligible_amount_minor', 'approved_amount_minor', 'fee_minor', 'net_payout_minor', 'currency_code', 'request_date', 'required_payout_date', 'reason_text', 'is_full_balance', 'status', 'eligibility_status', 'review_status', 'approval_status', 'payment_status', 'eligibility_snapshot', 'restriction_snapshot', 'submitted_at', 'submitted_by_user_id', 'reviewed_at', 'reviewed_by_user_id', 'approved_at', 'approved_by_user_id', 'rejected_at', 'rejected_by_user_id', 'rejection_reason', 'cancelled_at', 'cancelled_by_user_id', 'cancellation_reason', 'expired_at', 'total_paid_minor', 'outstanding_approved_minor', 'reservation_hold_id', 'settled_at', 'created_by_user_id'])]
class WithdrawalRequest extends Model
{
    /** @use HasFactory<WithdrawalRequestFactory> */
    use HasFactory, HasUuids;

    protected $attributes = ['status' => 'draft', 'eligibility_status' => 'pending', 'review_status' => 'pending', 'approval_status' => 'pending', 'payment_status' => 'not_approved', 'total_paid_minor' => 0, 'outstanding_approved_minor' => 0];

    protected static function booted(): void
    {
        static::deleting(fn (): never => throw new \LogicException('Withdrawal requests cannot be deleted.'));
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

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsTo<WithdrawalProduct, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(WithdrawalProduct::class, 'withdrawal_product_id');
    }

    /** @return BelongsTo<WithdrawalReason, $this> */
    public function reason(): BelongsTo
    {
        return $this->belongsTo(WithdrawalReason::class, 'withdrawal_reason_id');
    }

    /** @return BelongsTo<Wallet, $this> */
    public function savingsWallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'savings_wallet_id');
    }

    /** @return BelongsTo<SavingsHold, $this> */
    public function reservationHold(): BelongsTo
    {
        return $this->belongsTo(SavingsHold::class, 'reservation_hold_id');
    }

    /** @return HasOne<WithdrawalRequestTerm, $this> */
    public function terms(): HasOne
    {
        return $this->hasOne(WithdrawalRequestTerm::class);
    }

    /** @return HasMany<WithdrawalApproval, $this> */
    public function approvals(): HasMany
    {
        return $this->hasMany(WithdrawalApproval::class);
    }

    /** @return HasMany<WithdrawalPayout, $this> */
    public function payouts(): HasMany
    {
        return $this->hasMany(WithdrawalPayout::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['status' => WithdrawalRequestStatus::class, 'request_date' => 'date', 'required_payout_date' => 'date', 'is_full_balance' => 'boolean', 'eligibility_snapshot' => 'array', 'restriction_snapshot' => 'array', 'submitted_at' => 'datetime', 'reviewed_at' => 'datetime', 'approved_at' => 'datetime', 'rejected_at' => 'datetime', 'cancelled_at' => 'datetime', 'expired_at' => 'datetime', 'settled_at' => 'datetime'];
    }
}
