<?php

namespace App\Domain\Expenses\Models;

use App\Domain\Accounting\Models\FinancialTransaction;
use App\Domain\Expenses\Enums\PettyCashAdvanceStatus;
use App\Domain\Organisations\Models\Branch;
use App\Domain\Organisations\Models\Organisation;
use App\Domain\Wallets\Models\Wallet;
use App\Models\User;
use Database\Factories\Domain\Expenses\Models\PettyCashAdvanceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property PettyCashAdvanceStatus $status
 * @property Carbon|null $retirement_due_date
 */
#[Fillable(['public_id', 'organisation_id', 'branch_id', 'advance_number', 'expense_request_id', 'custodian_user_id', 'recipient_user_id', 'petty_cash_wallet_id', 'funding_wallet_id', 'requested_amount_minor', 'approved_amount_minor', 'disbursed_amount_minor', 'retired_amount_minor', 'refundable_amount_minor', 'shortage_minor', 'currency_code', 'purpose', 'status', 'requested_at', 'requested_by_user_id', 'approved_at', 'approved_by_user_id', 'disbursed_at', 'disbursed_by_user_id', 'retirement_due_date', 'retired_at', 'retired_by_user_id', 'closed_at', 'financial_transaction_id', 'idempotency_key', 'request_fingerprint', 'rejection_reason', 'reversal_reason', 'notes'])]
class PettyCashAdvance extends Model
{
    /** @use HasFactory<PettyCashAdvanceFactory> */
    use HasFactory, HasUuids;

    protected $attributes = ['status' => 'draft', 'disbursed_amount_minor' => 0, 'retired_amount_minor' => 0, 'refundable_amount_minor' => 0, 'shortage_minor' => 0];

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    protected static function booted(): void
    {
        static::deleting(fn () => throw new \LogicException('Petty cash advances cannot be deleted.'));
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

    /** @return BelongsTo<ExpenseRequest, $this> */
    public function expenseRequest(): BelongsTo
    {
        return $this->belongsTo(ExpenseRequest::class);
    }

    /** @return BelongsTo<User, $this> */
    public function custodian(): BelongsTo
    {
        return $this->belongsTo(User::class, 'custodian_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }

    /** @return BelongsTo<Wallet, $this> */
    public function pettyCashWallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'petty_cash_wallet_id');
    }

    /** @return BelongsTo<Wallet, $this> */
    public function fundingWallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'funding_wallet_id');
    }

    /** @return BelongsTo<FinancialTransaction, $this> */
    public function financialTransaction(): BelongsTo
    {
        return $this->belongsTo(FinancialTransaction::class);
    }

    /** @return HasMany<PettyCashRetirement, $this> */
    public function retirements(): HasMany
    {
        return $this->hasMany(PettyCashRetirement::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['status' => PettyCashAdvanceStatus::class, 'requested_at' => 'datetime', 'approved_at' => 'datetime', 'disbursed_at' => 'datetime', 'retirement_due_date' => 'date', 'retired_at' => 'datetime', 'closed_at' => 'datetime'];
    }
}
