<?php

namespace App\Domain\Wallets\Models;

use App\Domain\Accounting\Models\LedgerAccount;
use App\Domain\Accounting\Models\TransactionEntry;
use App\Domain\Organisations\Models\Branch;
use App\Domain\Organisations\Models\Organisation;
use App\Domain\Wallets\Enums\WalletStatus;
use App\Domain\Wallets\Enums\WalletType;
use App\Models\User;
use Database\Factories\Domain\Wallets\Models\WalletFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property WalletType $wallet_type
 * @property WalletStatus $status
 * @property bool $is_system_wallet
 * @property Carbon $opened_at
 * @property Carbon|null $closed_at
 */
#[Fillable([
    'public_id', 'organisation_id', 'branch_id', 'walletable_type',
    'walletable_id', 'ledger_account_id', 'wallet_type', 'name', 'code',
    'currency_code', 'status', 'is_system_wallet', 'opened_at', 'closed_at',
    'created_by_user_id', 'active_owner_key',
])]
class Wallet extends Model
{
    /** @use HasFactory<WalletFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $attributes = [
        'status' => 'pending',
        'is_system_wallet' => false,
    ];

    protected static function booted(): void
    {
        static::deleting(function (self $wallet): void {
            if ($wallet->entries()->exists()) {
                throw new \LogicException('A wallet with entries cannot be deleted.');
            }
        });
    }

    protected static function newFactory(): WalletFactory
    {
        return WalletFactory::new();
    }

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public static function activeOwnerKey(
        int $organisationId,
        string $ownerType,
        int $ownerId,
        WalletType $walletType,
        string $currencyCode,
    ): string {
        return implode(':', [
            $organisationId, $ownerType, $ownerId, $walletType->value,
            strtoupper($currencyCode),
        ]);
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

    /** @return MorphTo<Model, $this> */
    public function walletable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<LedgerAccount, $this> */
    public function ledgerAccount(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class);
    }

    /** @return HasMany<TransactionEntry, $this> */
    public function entries(): HasMany
    {
        return $this->hasMany(TransactionEntry::class);
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
            'wallet_type' => WalletType::class,
            'status' => WalletStatus::class,
            'is_system_wallet' => 'boolean',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }
}
