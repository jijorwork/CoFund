<?php

namespace App\Domain\Organisations\Models;

use App\Domain\Accounting\Models\AccountingPeriod;
use App\Domain\Accounting\Models\FinancialTransaction;
use App\Domain\Accounting\Models\LedgerAccount;
use App\Domain\Contributions\Models\ContributionPlan;
use App\Domain\Contributions\Models\MemberPlanEnrolment;
use App\Domain\Identity\Models\OrganisationUserRole;
use App\Domain\Membership\Models\Group;
use App\Domain\Membership\Models\Member;
use App\Domain\Organisations\Enums\OrganisationStatus;
use App\Domain\Organisations\Enums\OrganisationType;
use App\Domain\Subscriptions\Models\OrganisationSubscription;
use App\Domain\Wallets\Models\Wallet;
use App\Models\User;
use Database\Factories\Domain\Organisations\Models\OrganisationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property OrganisationStatus $status
 */
#[Fillable([
    'public_id',
    'name',
    'code',
    'organisation_type',
    'phone',
    'email',
    'address',
    'logo_path',
    'currency_code',
    'timezone',
    'status',
])]
class Organisation extends Model
{
    /** @use HasFactory<OrganisationFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $attributes = [
        'currency_code' => 'GHS',
        'timezone' => 'UTC',
        'status' => 'pending',
    ];

    protected static function newFactory(): OrganisationFactory
    {
        return OrganisationFactory::new();
    }

    /**
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    /**
     * @param  Builder<Organisation>  $query
     * @return Builder<Organisation>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', OrganisationStatus::Active);
    }

    /**
     * @return HasMany<Branch, $this>
     */
    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organisation_users')
            ->withPivot(['id', 'status', 'joined_at'])
            ->withTimestamps();
    }

    /**
     * @return HasMany<OrganisationUser, $this>
     */
    public function organisationAccesses(): HasMany
    {
        return $this->hasMany(OrganisationUser::class);
    }

    /** @return HasMany<OrganisationStatusHistory, $this> */
    public function statusHistories(): HasMany
    {
        return $this->hasMany(OrganisationStatusHistory::class)->latest();
    }

    /** @return HasMany<OrganisationSubscription, $this> */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(OrganisationSubscription::class);
    }

    /**
     * @return HasMany<OrganisationSetting, $this>
     */
    public function settings(): HasMany
    {
        return $this->hasMany(OrganisationSetting::class);
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

    /** @return HasMany<LedgerAccount, $this> */
    public function ledgerAccounts(): HasMany
    {
        return $this->hasMany(LedgerAccount::class);
    }

    /** @return HasMany<FinancialTransaction, $this> */
    public function financialTransactions(): HasMany
    {
        return $this->hasMany(FinancialTransaction::class);
    }

    /** @return HasMany<Wallet, $this> */
    public function wallets(): HasMany
    {
        return $this->hasMany(Wallet::class);
    }

    /** @return HasMany<AccountingPeriod, $this> */
    public function accountingPeriods(): HasMany
    {
        return $this->hasMany(AccountingPeriod::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'organisation_type' => OrganisationType::class,
            'status' => OrganisationStatus::class,
        ];
    }
}
