<?php

namespace App\Domain\Membership\Models;

use App\Domain\Contributions\Models\ContributionPlan;
use App\Domain\Contributions\Models\MemberPlanEnrolment;
use App\Domain\Membership\Enums\GroupStatus;
use App\Domain\Membership\Enums\GroupType;
use App\Domain\Organisations\Models\Branch;
use App\Domain\Organisations\Models\Organisation;
use App\Models\User;
use Database\Factories\Domain\Membership\Models\GroupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property GroupStatus $status
 * @property GroupType $group_type
 * @property-read int|null $active_members_count
 */
#[Fillable([
    'public_id', 'organisation_id', 'branch_id', 'name', 'code', 'group_type',
    'description', 'status', 'created_by_user_id',
])]
class Group extends Model
{
    /** @use HasFactory<GroupFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $attributes = ['status' => 'active'];

    protected static function newFactory(): GroupFactory
    {
        return GroupFactory::new();
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

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** @return HasMany<GroupMembership, $this> */
    public function memberships(): HasMany
    {
        return $this->hasMany(GroupMembership::class);
    }

    /** @return HasMany<ContributionPlan, $this> */
    public function contributionPlans(): HasMany
    {
        return $this->hasMany(ContributionPlan::class);
    }

    /** @return HasMany<MemberPlanEnrolment, $this> */
    public function contributionEnrolments(): HasMany
    {
        return $this->hasMany(MemberPlanEnrolment::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['group_type' => GroupType::class, 'status' => GroupStatus::class];
    }
}
