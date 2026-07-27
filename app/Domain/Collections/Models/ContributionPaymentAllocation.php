<?php

namespace App\Domain\Collections\Models;

use App\Domain\Collections\Enums\PaymentAllocationStatus;
use App\Domain\Contributions\Models\ContributionObligation;
use App\Domain\Contributions\Models\MemberPlanEnrolment;
use App\Domain\Membership\Models\Member;
use App\Domain\Organisations\Models\Organisation;
use App\Models\User;
use Database\Factories\Domain\Collections\Models\ContributionPaymentAllocationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property PaymentAllocationStatus $status
 * @property Carbon $allocated_at
 */
#[Fillable([
    'public_id', 'organisation_id', 'contribution_payment_id',
    'contribution_obligation_id', 'member_plan_enrolment_id', 'member_id',
    'amount_minor', 'currency_code', 'allocation_order', 'status',
    'reversed_allocation_id', 'reversal_allocation_id', 'allocated_at',
    'created_by_user_id',
])]
class ContributionPaymentAllocation extends Model
{
    /** @use HasFactory<ContributionPaymentAllocationFactory> */
    use HasFactory, HasUuids;

    protected static function booted(): void
    {
        static::updating(function (self $allocation): void {
            if (array_diff(array_keys($allocation->getDirty()), ['status', 'reversal_allocation_id', 'updated_at']) !== []) {
                throw new \LogicException('Payment allocations are immutable.');
            }
        });
        static::deleting(fn (): never => throw new \LogicException('Payment allocations cannot be deleted.'));
    }

    protected static function newFactory(): ContributionPaymentAllocationFactory
    {
        return ContributionPaymentAllocationFactory::new();
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

    /** @return BelongsTo<ContributionPayment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(ContributionPayment::class, 'contribution_payment_id');
    }

    /** @return BelongsTo<ContributionObligation, $this> */
    public function obligation(): BelongsTo
    {
        return $this->belongsTo(ContributionObligation::class, 'contribution_obligation_id');
    }

    /** @return BelongsTo<MemberPlanEnrolment, $this> */
    public function enrolment(): BelongsTo
    {
        return $this->belongsTo(MemberPlanEnrolment::class, 'member_plan_enrolment_id');
    }

    /** @return BelongsTo<Member, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** @return BelongsTo<ContributionPaymentAllocation, $this> */
    public function reversalAllocation(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_allocation_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['status' => PaymentAllocationStatus::class, 'allocated_at' => 'datetime'];
    }
}
