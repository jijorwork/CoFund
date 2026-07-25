<?php

namespace App\Domain\Organisations\Models;

use App\Domain\Contributions\Models\ContributionPlan;
use App\Domain\Contributions\Models\MemberPlanEnrolment;
use App\Domain\Identity\Models\OrganisationUserRole;
use App\Domain\Membership\Models\Group;
use App\Domain\Membership\Models\Member;
use App\Domain\Organisations\Enums\BranchStatus;
use Database\Factories\Domain\Organisations\Models\BranchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property BranchStatus $status
 */
#[Fillable([
    'public_id',
    'organisation_id',
    'name',
    'code',
    'phone',
    'email',
    'address',
    'status',
])]
class Branch extends Model
{
    /** @use HasFactory<BranchFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $attributes = [
        'status' => 'active',
    ];

    protected static function newFactory(): BranchFactory
    {
        return BranchFactory::new();
    }

    /**
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    /**
     * @param  Builder<Branch>  $query
     * @return Builder<Branch>
     */
    public function scopeForOrganisation(Builder $query, Organisation $organisation): Builder
    {
        return $query->whereBelongsTo($organisation);
    }

    /**
     * @param  Builder<Branch>  $query
     * @return Builder<Branch>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', BranchStatus::Active);
    }

    /**
     * @return BelongsTo<Organisation, $this>
     */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    /**
     * @return HasMany<OrganisationUserRole, $this>
     */
    public function userRoleAssignments(): HasMany
    {
        return $this->hasMany(OrganisationUserRole::class);
    }

    /** @return HasMany<Member, $this> */
    public function members(): HasMany
    {
        return $this->hasMany(Member::class);
    }

    /** @return HasMany<Group, $this> */
    public function groups(): HasMany
    {
        return $this->hasMany(Group::class);
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

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => BranchStatus::class,
        ];
    }
}
