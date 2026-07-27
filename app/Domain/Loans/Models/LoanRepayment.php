<?php

namespace App\Domain\Loans\Models;

use App\Domain\Accounting\Models\FinancialTransaction;
use App\Domain\Collections\Models\Receipt;
use App\Domain\Loans\Enums\LoanRepaymentStatus;
use App\Domain\Wallets\Models\Wallet;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property LoanRepaymentStatus $status
 * @property Carbon $repayment_date
 * @property Carbon|null $reversed_at
 */
#[Fillable(['public_id', 'organisation_id', 'branch_id', 'loan_id', 'member_id', 'source_wallet_id', 'financial_transaction_id', 'receipt_id', 'repayment_number', 'amount_minor', 'principal_allocated_minor', 'interest_allocated_minor', 'fee_allocated_minor', 'penalty_allocated_minor', 'unallocated_minor', 'currency_code', 'payment_method', 'repayment_date', 'status', 'collector_user_id', 'idempotency_key', 'request_fingerprint', 'reversal_transaction_id', 'reversal_reason', 'reversed_at'])]
class LoanRepayment extends Model
{
    use HasUuids;

    protected $attributes = ['status' => 'posted'];

    protected static function booted(): void
    {
        static::deleting(fn (): never => throw new \LogicException('Loan repayments cannot be deleted.'));
    }

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    /** @return BelongsTo<Loan, $this> */
    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    /** @return BelongsTo<Wallet, $this> */
    public function sourceWallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'source_wallet_id');
    }

    /** @return BelongsTo<FinancialTransaction, $this> */
    public function financialTransaction(): BelongsTo
    {
        return $this->belongsTo(FinancialTransaction::class);
    }

    /** @return BelongsTo<Receipt, $this> */
    public function receipt(): BelongsTo
    {
        return $this->belongsTo(Receipt::class);
    }

    /** @return HasMany<LoanRepaymentAllocation, $this> */
    public function allocations(): HasMany
    {
        return $this->hasMany(LoanRepaymentAllocation::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['status' => LoanRepaymentStatus::class, 'repayment_date' => 'date', 'reversed_at' => 'datetime'];
    }
}
