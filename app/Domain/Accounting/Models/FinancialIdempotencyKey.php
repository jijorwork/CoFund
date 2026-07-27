<?php

namespace App\Domain\Accounting\Models;

use App\Domain\Accounting\Enums\IdempotencyStatus;
use App\Domain\Organisations\Models\Organisation;
use Database\Factories\Domain\Accounting\Models\FinancialIdempotencyKeyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property IdempotencyStatus $status */
#[Fillable([
    'organisation_id', 'idempotency_key', 'request_fingerprint',
    'financial_transaction_id', 'status', 'locked_at', 'completed_at',
    'expires_at',
])]
class FinancialIdempotencyKey extends Model
{
    /** @use HasFactory<FinancialIdempotencyKeyFactory> */
    use HasFactory;

    protected $attributes = ['status' => 'processing'];

    protected static function newFactory(): FinancialIdempotencyKeyFactory
    {
        return FinancialIdempotencyKeyFactory::new();
    }

    /** @return BelongsTo<Organisation, $this> */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    /** @return BelongsTo<FinancialTransaction, $this> */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(FinancialTransaction::class, 'financial_transaction_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => IdempotencyStatus::class,
            'locked_at' => 'datetime',
            'completed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }
}
