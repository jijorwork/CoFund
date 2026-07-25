<?php

namespace App\Domain\Contributions\Models;

use App\Domain\Contributions\Enums\ApprovalStatus;
use App\Domain\Contributions\Enums\EnrolmentStatus;
use App\Domain\Membership\Models\Group;
use App\Domain\Membership\Models\Member;
use App\Domain\Organisations\Models\Branch;
use App\Domain\Organisations\Models\Organisation;
use App\Models\User;
use Database\Factories\Domain\Contributions\Models\MemberPlanEnrolmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property EnrolmentStatus $status
 * @property ApprovalStatus $approval_status
 * @property Carbon $start_date
 * @property Carbon|null $end_date
 * @property Carbon|null $next_due_date
 */
#[Fillable([
    'public_id', 'organisation_id', 'contribution_plan_id', 'member_id',
    'branch_id', 'group_id', 'selected_amount_minor', 'currency_code',
    'start_date', 'end_date', 'next_due_date', 'status', 'approval_status',
    'approved_by_user_id', 'approved_at', 'suspended_at',
    'suspended_by_user_id', 'suspension_reason', 'ended_at',
    'ended_by_user_id', 'end_reason', 'created_by_user_id',
    'active_enrolment_key',
])]
class MemberPlanEnrolment extends Model
{
    /** @use HasFactory<MemberPlanEnrolmentFactory> */
    use HasFactory, HasUuids;

    protected $attributes = ['status' => 'pending', 'approval_status' => 'not_required'];

    protected static function newFactory(): MemberPlanEnrolmentFactory
    {
        return MemberPlanEnrolmentFactory::new();
    }

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public static function activeEnrolmentKey(ContributionPlan $plan, Member $member): string
    {
        return $plan->getKey().':'.$member->getKey();
    }

    /** @return BelongsTo<Organisation, $this> */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    /** @return BelongsTo<ContributionPlan, $this> */
    public function contributionPlan(): BelongsTo
    {
        return $this->belongsTo(ContributionPlan::class);
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

    /** @return BelongsTo<Group, $this> */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    /** @return HasMany<ContributionObligation, $this> */
    public function obligations(): HasMany
    {
        return $this->hasMany(ContributionObligation::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => EnrolmentStatus::class,
            'approval_status' => ApprovalStatus::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'next_due_date' => 'date',
            'approved_at' => 'datetime',
            'suspended_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }
}
