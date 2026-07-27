<?php

namespace App\Domain\Reconciliation\Models;

use App\Domain\Accounting\Models\FinancialTransaction;
use App\Domain\Organisations\Models\Branch;
use App\Domain\Organisations\Models\Organisation;
use App\Domain\Reconciliation\Enums\CashHandoverStatus;
use App\Domain\Wallets\Models\Wallet;
use App\Models\User;
use Database\Factories\Domain\Reconciliation\Models\CollectorCashHandoverFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property CashHandoverStatus $status
 * @property Carbon|null $handed_over_at
 * @property Carbon|null $received_at
 * @property Carbon|null $approved_at
 */
#[Fillable([
    'public_id', 'organisation_id', 'branch_id', 'collector_collection_session_id',
    'handover_number', 'collector_user_id', 'collector_wallet_id',
    'receiving_user_id', 'destination_wallet_id', 'declared_cash_minor',
    'accepted_cash_minor', 'shortage_minor', 'overage_minor', 'currency_code',
    'financial_transaction_id', 'status', 'handed_over_at', 'received_at',
    'submitted_by_user_id', 'received_by_user_id', 'approved_by_user_id',
    'approved_at', 'rejection_reason', 'idempotency_key', 'request_fingerprint',
    'reversed_handover_id', 'reversal_handover_id', 'reversal_reason',
])]
class CollectorCashHandover extends Model
{
    /** @use HasFactory<CollectorCashHandoverFactory> */
    use HasFactory, HasUuids;

    protected $attributes = ['status' => 'draft', 'shortage_minor' => 0, 'overage_minor' => 0];

    protected static function booted(): void
    {
        static::updating(function (self $handover): void {
            if ($handover->getOriginal('status') === CashHandoverStatus::Approved->value
                && array_diff(array_keys($handover->getDirty()), ['status', 'reversal_handover_id', 'reversal_reason', 'updated_at']) !== []) {
                throw new \LogicException('Approved cash handovers are immutable.');
            }
        });
        static::deleting(fn (): never => throw new \LogicException('Cash handovers cannot be deleted.'));
    }

    protected static function newFactory(): CollectorCashHandoverFactory
    {
        return CollectorCashHandoverFactory::new();
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

    /** @return BelongsTo<CollectorCollectionSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(CollectorCollectionSession::class, 'collector_collection_session_id');
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

    /** @return BelongsTo<Wallet, $this> */
    public function destinationWallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'destination_wallet_id');
    }

    /** @return BelongsTo<FinancialTransaction, $this> */
    public function financialTransaction(): BelongsTo
    {
        return $this->belongsTo(FinancialTransaction::class);
    }

    /** @return HasMany<CollectorCashVariance, $this> */
    public function variances(): HasMany
    {
        return $this->hasMany(CollectorCashVariance::class);
    }

    /** @return BelongsTo<CollectorCashHandover, $this> */
    public function reversalHandover(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_handover_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => CashHandoverStatus::class,
            'handed_over_at' => 'datetime',
            'received_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }
}
