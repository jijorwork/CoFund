<?php

namespace App\Domain\Membership\Models;

use App\Domain\Accounting\Models\TransactionEntry;
use App\Domain\Collections\Models\ContributionPayment;
use App\Domain\Contributions\Models\ContributionObligation;
use App\Domain\Contributions\Models\MemberPlanEnrolment;
use App\Domain\Loans\Models\Loan;
use App\Domain\Loans\Models\LoanApplication;
use App\Domain\Membership\Enums\MemberGender;
use App\Domain\Membership\Enums\MemberStatus;
use App\Domain\Organisations\Models\Branch;
use App\Domain\Organisations\Models\Organisation;
use App\Domain\Wallets\Models\Wallet;
use App\Models\User;
use Database\Factories\Domain\Membership\Models\MemberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property MemberStatus $status
 * @property MemberGender|null $gender
 * @property Carbon $joined_at
 * @property Carbon|null $date_of_birth
 * @property Carbon|null $suspended_at
 * @property Carbon|null $exited_at
 * @property Carbon|null $deceased_at
 */
#[Fillable([
    'public_id', 'organisation_id', 'branch_id', 'user_id', 'membership_number',
    'first_name', 'middle_name', 'last_name', 'preferred_name', 'gender',
    'date_of_birth', 'phone', 'alternate_phone', 'email', 'national_id_type',
    'national_id_number', 'occupation', 'residential_address', 'postal_address',
    'profile_photo_path', 'joined_at', 'status', 'notes', 'created_by_user_id',
    'suspended_at', 'suspended_by_user_id', 'suspension_reason', 'exited_at',
    'exit_reason', 'deceased_at', 'active_user_link_key',
])]
class Member extends Model
{
    /** @use HasFactory<MemberFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $attributes = ['status' => 'pending'];

    protected static function newFactory(): MemberFactory
    {
        return MemberFactory::new();
    }

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function fullName(): string
    {
        return collect([$this->first_name, $this->middle_name, $this->last_name])
            ->filter()
            ->implode(' ');
    }

    public static function activeUserLinkKey(Organisation $organisation, User $user): string
    {
        return $organisation->getKey().':'.$user->getKey();
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
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function suspendedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'suspended_by_user_id');
    }

    /** @return HasMany<MemberStatusHistory, $this> */
    public function statusHistories(): HasMany
    {
        return $this->hasMany(MemberStatusHistory::class);
    }

    /** @return HasMany<GroupMembership, $this> */
    public function groupMemberships(): HasMany
    {
        return $this->hasMany(GroupMembership::class);
    }

    /** @return HasMany<MemberContact, $this> */
    public function contacts(): HasMany
    {
        return $this->hasMany(MemberContact::class);
    }

    /** @return MorphMany<MemberDocument, $this> */
    public function documents(): MorphMany
    {
        return $this->morphMany(MemberDocument::class, 'documentable');
    }

    /** @return HasMany<MemberPlanEnrolment, $this> */
    public function contributionEnrolments(): HasMany
    {
        return $this->hasMany(MemberPlanEnrolment::class);
    }

    /** @return HasMany<ContributionObligation, $this> */
    public function contributionObligations(): HasMany
    {
        return $this->hasMany(ContributionObligation::class);
    }

    /** @return MorphMany<Wallet, $this> */
    public function wallets(): MorphMany
    {
        return $this->morphMany(Wallet::class, 'walletable');
    }

    /** @return HasMany<ContributionPayment, $this> */
    public function contributionPayments(): HasMany
    {
        return $this->hasMany(ContributionPayment::class);
    }

    /** @return HasMany<LoanApplication, $this> */
    public function loanApplications(): HasMany
    {
        return $this->hasMany(LoanApplication::class);
    }

    /** @return HasMany<Loan, $this> */
    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }

    /** @return HasMany<TransactionEntry, $this> */
    public function transactionEntries(): HasMany
    {
        return $this->hasMany(TransactionEntry::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => MemberStatus::class,
            'gender' => MemberGender::class,
            'date_of_birth' => 'date',
            'joined_at' => 'date',
            'suspended_at' => 'datetime',
            'exited_at' => 'date',
            'deceased_at' => 'date',
            'national_id_number' => 'encrypted',
        ];
    }
}
