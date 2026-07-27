<?php

namespace App\Domain\Contributions\Models;

use App\Domain\Collections\Models\ContributionPayment;
use App\Domain\Contributions\Enums\ContributionAmountType;
use App\Domain\Contributions\Enums\ContributionFrequency;
use App\Domain\Contributions\Enums\ContributionPlanStatus;
use App\Domain\Contributions\Enums\ContributionPlanType;
use App\Domain\Membership\Models\Group;
use App\Domain\Organisations\Models\Branch;
use App\Domain\Organisations\Models\Organisation;
use App\Models\User;
use Database\Factories\Domain\Contributions\Models\ContributionPlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property ContributionPlanType $plan_type
 * @property ContributionFrequency $frequency
 * @property ContributionAmountType $amount_type
 * @property ContributionPlanStatus $status
 * @property Carbon|null $start_date
 * @property Carbon|null $end_date
 */
#[Fillable([
    'public_id', 'organisation_id', 'branch_id', 'group_id', 'name', 'code',
    'description', 'plan_type', 'frequency', 'amount_type', 'fixed_amount_minor',
    'minimum_amount_minor', 'maximum_amount_minor', 'currency_code', 'start_date',
    'end_date', 'collection_day_of_week', 'collection_day_of_month',
    'grace_period_days', 'allow_partial_payments', 'allow_advance_payments',
    'allow_overpayments', 'requires_approval_to_join', 'status',
    'created_by_user_id', 'activated_at', 'suspended_at', 'closed_at',
])]
class ContributionPlan extends Model
{
    /** @use HasFactory<ContributionPlanFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $attributes = [
        'status' => 'draft',
        'currency_code' => 'GHS',
        'grace_period_days' => 0,
    ];

    protected static function newFactory(): ContributionPlanFactory
    {
        return ContributionPlanFactory::new();
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

    /** @return HasMany<MemberPlanEnrolment, $this> */
    public function enrolments(): HasMany
    {
        return $this->hasMany(MemberPlanEnrolment::class);
    }

    /** @return HasMany<ContributionObligation, $this> */
    public function obligations(): HasMany
    {
        return $this->hasMany(ContributionObligation::class);
    }

    /** @return HasMany<ContributionPayment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(ContributionPayment::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'plan_type' => ContributionPlanType::class,
            'frequency' => ContributionFrequency::class,
            'amount_type' => ContributionAmountType::class,
            'status' => ContributionPlanStatus::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'allow_partial_payments' => 'boolean',
            'allow_advance_payments' => 'boolean',
            'allow_overpayments' => 'boolean',
            'requires_approval_to_join' => 'boolean',
            'activated_at' => 'datetime',
            'suspended_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }
}
