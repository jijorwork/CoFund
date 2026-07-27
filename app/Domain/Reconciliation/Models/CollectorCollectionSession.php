<?php

namespace App\Domain\Reconciliation\Models;

use App\Domain\Collections\Models\ContributionPayment;
use App\Domain\Organisations\Models\Branch;
use App\Domain\Organisations\Models\Organisation;
use App\Domain\Reconciliation\Enums\CollectorSessionStatus;
use App\Domain\Wallets\Models\Wallet;
use App\Models\User;
use Database\Factories\Domain\Reconciliation\Models\CollectorCollectionSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property CollectorSessionStatus $status
 * @property Carbon $session_date
 * @property Carbon $opened_at
 * @property Carbon|null $closed_at
 * @property Carbon|null $submitted_at
 * @property Carbon|null $approved_at
 */
#[Fillable([
    'public_id', 'organisation_id', 'branch_id', 'collector_user_id',
    'collector_wallet_id', 'session_number', 'session_date', 'opened_at',
    'closed_at', 'status', 'expected_cash_minor', 'expected_non_cash_minor',
    'declared_cash_minor', 'shortage_minor', 'overage_minor', 'currency_code',
    'opened_by_user_id', 'closed_by_user_id', 'submitted_at',
    'submitted_by_user_id', 'approved_at', 'approved_by_user_id', 'rejected_at',
    'rejected_by_user_id', 'rejection_reason', 'notes', 'active_wallet_key',
])]
class CollectorCollectionSession extends Model
{
    /** @use HasFactory<CollectorCollectionSessionFactory> */
    use HasFactory, HasUuids;

    protected $attributes = [
        'status' => 'open',
        'expected_cash_minor' => 0,
        'expected_non_cash_minor' => 0,
        'shortage_minor' => 0,
        'overage_minor' => 0,
    ];

    protected static function booted(): void
    {
        static::deleting(fn (): never => throw new \LogicException('Collector sessions cannot be deleted.'));
    }

    protected static function newFactory(): CollectorCollectionSessionFactory
    {
        return CollectorCollectionSessionFactory::new();
    }

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public static function activeWalletKey(int $walletId): string
    {
        return (string) $walletId;
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
    public function collector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collector_user_id');
    }

    /** @return BelongsTo<Wallet, $this> */
    public function collectorWallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'collector_wallet_id');
    }

    /** @return HasMany<ContributionPayment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(ContributionPayment::class);
    }

    /** @return HasMany<CollectorCashDeclaration, $this> */
    public function declarations(): HasMany
    {
        return $this->hasMany(CollectorCashDeclaration::class);
    }

    /** @return HasMany<CollectorCashHandover, $this> */
    public function handovers(): HasMany
    {
        return $this->hasMany(CollectorCashHandover::class);
    }

    /** @return HasMany<CollectorCashVariance, $this> */
    public function variances(): HasMany
    {
        return $this->hasMany(CollectorCashVariance::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => CollectorSessionStatus::class,
            'session_date' => 'date',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }
}
