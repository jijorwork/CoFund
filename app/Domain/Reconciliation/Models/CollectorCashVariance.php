<?php

namespace App\Domain\Reconciliation\Models;

use App\Domain\Accounting\Models\FinancialTransaction;
use App\Domain\Organisations\Models\Branch;
use App\Domain\Organisations\Models\Organisation;
use App\Domain\Reconciliation\Enums\CashVarianceResolutionType;
use App\Domain\Reconciliation\Enums\CashVarianceStatus;
use App\Domain\Reconciliation\Enums\CashVarianceType;
use App\Domain\Wallets\Models\Wallet;
use App\Models\User;
use Database\Factories\Domain\Reconciliation\Models\CollectorCashVarianceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property CashVarianceType $variance_type
 * @property CashVarianceStatus $status
 * @property CashVarianceResolutionType|null $resolution_type
 * @property Carbon $reported_at
 * @property Carbon|null $resolved_at
 */
#[Fillable([
    'public_id', 'organisation_id', 'branch_id', 'collector_collection_session_id',
    'collector_cash_handover_id', 'collector_user_id', 'collector_wallet_id',
    'variance_type', 'amount_minor', 'currency_code', 'status', 'reason',
    'reported_at', 'reported_by_user_id', 'resolved_at', 'resolved_by_user_id',
    'resolution_type', 'resolution_financial_transaction_id', 'resolution_notes',
])]
class CollectorCashVariance extends Model
{
    /** @use HasFactory<CollectorCashVarianceFactory> */
    use HasFactory, HasUuids;

    protected $attributes = ['status' => 'open'];

    protected static function booted(): void
    {
        static::deleting(fn (): never => throw new \LogicException('Cash variances cannot be deleted.'));
    }

    protected static function newFactory(): CollectorCashVarianceFactory
    {
        return CollectorCashVarianceFactory::new();
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

    /** @return BelongsTo<CollectorCashHandover, $this> */
    public function handover(): BelongsTo
    {
        return $this->belongsTo(CollectorCashHandover::class, 'collector_cash_handover_id');
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

    /** @return BelongsTo<FinancialTransaction, $this> */
    public function resolutionFinancialTransaction(): BelongsTo
    {
        return $this->belongsTo(FinancialTransaction::class, 'resolution_financial_transaction_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'variance_type' => CashVarianceType::class,
            'status' => CashVarianceStatus::class,
            'resolution_type' => CashVarianceResolutionType::class,
            'reported_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }
}
