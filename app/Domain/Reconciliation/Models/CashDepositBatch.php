<?php

namespace App\Domain\Reconciliation\Models;

use App\Domain\Accounting\Models\FinancialTransaction;
use App\Domain\Organisations\Models\Branch;
use App\Domain\Organisations\Models\Organisation;
use App\Domain\Reconciliation\Enums\CashDepositMethod;
use App\Domain\Reconciliation\Enums\CashDepositStatus;
use App\Domain\Wallets\Models\Wallet;
use Database\Factories\Domain\Reconciliation\Models\CashDepositBatchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property CashDepositMethod $deposit_method
 * @property CashDepositStatus $status
 * @property Carbon $deposit_date
 * @property Carbon|null $confirmed_at
 */
#[Fillable([
    'public_id', 'organisation_id', 'branch_id', 'deposit_number',
    'source_wallet_id', 'destination_wallet_id', 'deposit_method', 'deposit_date',
    'amount_minor', 'currency_code', 'status', 'bank_reference',
    'mobile_money_reference', 'deposit_slip_number', 'deposited_by_user_id',
    'confirmed_by_user_id', 'confirmed_at', 'financial_transaction_id', 'notes',
    'idempotency_key', 'request_fingerprint', 'reversed_deposit_id',
    'reversal_deposit_id', 'reversal_reason',
])]
class CashDepositBatch extends Model
{
    /** @use HasFactory<CashDepositBatchFactory> */
    use HasFactory, HasUuids;

    protected $attributes = ['status' => 'draft'];

    protected static function booted(): void
    {
        static::updating(function (self $deposit): void {
            if ($deposit->getOriginal('status') === CashDepositStatus::Confirmed->value
                && array_diff(array_keys($deposit->getDirty()), ['status', 'reversal_deposit_id', 'reversal_reason', 'updated_at']) !== []) {
                throw new \LogicException('Confirmed cash deposits are immutable.');
            }
        });
        static::deleting(fn (): never => throw new \LogicException('Cash deposits cannot be deleted.'));
    }

    protected static function newFactory(): CashDepositBatchFactory
    {
        return CashDepositBatchFactory::new();
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

    /** @return BelongsTo<Wallet, $this> */
    public function sourceWallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'source_wallet_id');
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

    /** @return BelongsTo<CashDepositBatch, $this> */
    public function reversalDeposit(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_deposit_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'deposit_method' => CashDepositMethod::class,
            'status' => CashDepositStatus::class,
            'deposit_date' => 'date',
            'confirmed_at' => 'datetime',
        ];
    }
}
