<?php

namespace App\Domain\Accounting\Models;

use App\Domain\Accounting\Enums\FinancialReferenceType;
use App\Domain\Accounting\Enums\FinancialTransactionStatus;
use App\Domain\Accounting\Enums\FinancialTransactionType;
use App\Domain\Organisations\Models\Branch;
use App\Domain\Organisations\Models\Organisation;
use App\Models\User;
use Database\Factories\Domain\Accounting\Models\FinancialTransactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property FinancialTransactionType $transaction_type
 * @property FinancialTransactionStatus $status
 * @property FinancialReferenceType|null $reference_type
 * @property Carbon $transaction_date
 * @property Carbon|null $posted_at
 * @property array<string, mixed>|null $metadata
 */
#[Fillable([
    'public_id', 'organisation_id', 'branch_id', 'transaction_number',
    'transaction_type', 'status', 'currency_code', 'transaction_date',
    'posted_at', 'description', 'reference_type', 'reference_id',
    'external_reference', 'idempotency_key', 'request_fingerprint',
    'original_transaction_id', 'reversal_transaction_id', 'reversal_reason',
    'created_by_user_id', 'posted_by_user_id', 'reversed_by_user_id', 'metadata',
])]
class FinancialTransaction extends Model
{
    /** @use HasFactory<FinancialTransactionFactory> */
    use HasFactory, HasUuids;

    protected $attributes = ['status' => 'draft'];

    protected static function booted(): void
    {
        static::updating(function (self $transaction): void {
            if (! in_array($transaction->getRawOriginal('status'), ['posted', 'reversed'], true)) {
                return;
            }

            $allowed = [
                'status', 'reversal_transaction_id', 'reversal_reason',
                'reversed_by_user_id', 'updated_at',
            ];

            if (array_diff(array_keys($transaction->getDirty()), $allowed) !== []) {
                throw new LogicException('Posted transactions are immutable.');
            }
        });

        static::deleting(function (self $transaction): void {
            if (in_array($transaction->status, [
                FinancialTransactionStatus::Posted,
                FinancialTransactionStatus::Reversed,
            ], true)) {
                throw new LogicException('Posted transactions cannot be deleted.');
            }
        });
    }

    protected static function newFactory(): FinancialTransactionFactory
    {
        return FinancialTransactionFactory::new();
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

    /** @return HasMany<TransactionEntry, $this> */
    public function entries(): HasMany
    {
        return $this->hasMany(TransactionEntry::class);
    }

    /** @return BelongsTo<FinancialTransaction, $this> */
    public function originalTransaction(): BelongsTo
    {
        return $this->belongsTo(self::class, 'original_transaction_id');
    }

    /** @return BelongsTo<FinancialTransaction, $this> */
    public function reversalTransaction(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_transaction_id');
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by_user_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'transaction_type' => FinancialTransactionType::class,
            'status' => FinancialTransactionStatus::class,
            'reference_type' => FinancialReferenceType::class,
            'transaction_date' => 'date',
            'posted_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
