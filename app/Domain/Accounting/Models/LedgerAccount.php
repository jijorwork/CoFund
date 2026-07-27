<?php

namespace App\Domain\Accounting\Models;

use App\Domain\Accounting\Enums\AccountCategory;
use App\Domain\Accounting\Enums\AccountPurpose;
use App\Domain\Accounting\Enums\LedgerAccountStatus;
use App\Domain\Accounting\Enums\NormalBalance;
use App\Domain\Organisations\Models\Branch;
use App\Domain\Organisations\Models\Organisation;
use App\Domain\Wallets\Models\Wallet;
use App\Models\User;
use Database\Factories\Domain\Accounting\Models\LedgerAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property AccountCategory $category
 * @property NormalBalance $normal_balance
 * @property AccountPurpose $purpose
 * @property LedgerAccountStatus $status
 * @property bool $allows_direct_posting
 * @property bool $is_system_account
 * @property Carbon|null $activated_at
 * @property Carbon|null $closed_at
 */
#[Fillable([
    'public_id', 'organisation_id', 'branch_id', 'parent_id', 'code', 'name',
    'description', 'category', 'normal_balance', 'purpose', 'currency_code',
    'allows_direct_posting', 'is_system_account', 'status',
    'created_by_user_id', 'activated_at', 'closed_at',
])]
class LedgerAccount extends Model
{
    /** @use HasFactory<LedgerAccountFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $attributes = [
        'status' => 'draft',
        'allows_direct_posting' => true,
        'is_system_account' => false,
    ];

    protected static function booted(): void
    {
        static::updating(function (self $account): void {
            $classification = [
                'category', 'normal_balance', 'purpose', 'currency_code',
            ];

            if ($account->entries()->exists() && $account->isDirty($classification)) {
                throw new LogicException('An account with entries cannot change classification.');
            }
        });

        static::deleting(function (self $account): void {
            if ($account->entries()->exists()) {
                throw new LogicException('An account with entries cannot be deleted.');
            }
        });
    }

    protected static function newFactory(): LedgerAccountFactory
    {
        return LedgerAccountFactory::new();
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

    /** @return BelongsTo<LedgerAccount, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<LedgerAccount, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /** @return HasMany<TransactionEntry, $this> */
    public function entries(): HasMany
    {
        return $this->hasMany(TransactionEntry::class);
    }

    /** @return HasMany<Wallet, $this> */
    public function wallets(): HasMany
    {
        return $this->hasMany(Wallet::class);
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'category' => AccountCategory::class,
            'normal_balance' => NormalBalance::class,
            'purpose' => AccountPurpose::class,
            'status' => LedgerAccountStatus::class,
            'allows_direct_posting' => 'boolean',
            'is_system_account' => 'boolean',
            'activated_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }
}
