<?php

namespace App\Domain\Expenses\Models;

use App\Domain\Accounting\Models\FinancialTransaction;
use App\Domain\Expenses\Enums\PettyCashRetirementStatus;
use App\Domain\Organisations\Models\Organisation;
use Database\Factories\Domain\Expenses\Models\PettyCashRetirementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @property PettyCashRetirementStatus $status */
#[Fillable(['public_id', 'organisation_id', 'branch_id', 'petty_cash_advance_id', 'retirement_number', 'submitted_amount_minor', 'accepted_expense_minor', 'returned_cash_minor', 'shortage_minor', 'currency_code', 'status', 'submitted_at', 'submitted_by_user_id', 'reviewed_at', 'reviewed_by_user_id', 'approved_at', 'approved_by_user_id', 'rejection_reason', 'financial_transaction_id', 'idempotency_key', 'request_fingerprint', 'reversal_reason'])]
class PettyCashRetirement extends Model
{
    /** @use HasFactory<PettyCashRetirementFactory> */
    use HasFactory, HasUuids;

    protected $attributes = ['status' => 'draft', 'accepted_expense_minor' => 0, 'returned_cash_minor' => 0, 'shortage_minor' => 0];

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    protected static function booted(): void
    {
        static::updating(function (self $m): void {
            if ($m->getOriginal('status') === PettyCashRetirementStatus::Approved->value && array_diff(array_keys($m->getDirty()), ['status', 'reversal_reason', 'updated_at']) !== []) {
                throw new \LogicException('Approved retirements are immutable.');
            }
        });
        static::deleting(fn () => throw new \LogicException('Retirements cannot be deleted.'));
    }

    /** @return BelongsTo<Organisation, $this> */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    /** @return BelongsTo<PettyCashAdvance, $this> */
    public function advance(): BelongsTo
    {
        return $this->belongsTo(PettyCashAdvance::class, 'petty_cash_advance_id');
    }

    /** @return HasMany<PettyCashRetirementItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(PettyCashRetirementItem::class)->orderBy('sequence_number');
    }

    /** @return BelongsTo<FinancialTransaction, $this> */
    public function financialTransaction(): BelongsTo
    {
        return $this->belongsTo(FinancialTransaction::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['status' => PettyCashRetirementStatus::class, 'submitted_at' => 'datetime', 'reviewed_at' => 'datetime', 'approved_at' => 'datetime'];
    }
}
