<?php

namespace App\Domain\Expenses\Models;

use App\Domain\Accounting\Models\FinancialTransaction;
use App\Domain\Expenses\Enums\ExpensePaymentMethod;
use App\Domain\Expenses\Enums\ExpensePaymentStatus;
use App\Domain\Expenses\Enums\ExpensePaymentType;
use App\Domain\Organisations\Models\Organisation;
use App\Domain\Wallets\Models\Wallet;
use App\Models\User;
use Database\Factories\Domain\Expenses\Models\ExpensePaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property ExpensePaymentType $payment_type
 * @property ExpensePaymentMethod $payment_method
 * @property ExpensePaymentStatus $status
 * @property Carbon $payment_date
 */
#[Fillable(['public_id', 'organisation_id', 'branch_id', 'expense_request_id', 'payment_number', 'payment_type', 'source_wallet_id', 'financial_transaction_id', 'amount_minor', 'currency_code', 'payment_method', 'payment_date', 'payee_name', 'payee_reference', 'external_reference', 'status', 'idempotency_key', 'request_fingerprint', 'paid_by_user_id', 'approved_by_user_id', 'reversed_payment_id', 'reversal_payment_id', 'reversal_reason'])]
class ExpensePayment extends Model
{
    /** @use HasFactory<ExpensePaymentFactory> */
    use HasFactory, HasUuids;

    protected $attributes = ['status' => 'pending'];

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    protected static function booted(): void
    {
        static::updating(function (self $m): void {
            if ($m->getOriginal('status') === ExpensePaymentStatus::Posted->value && array_diff(array_keys($m->getDirty()), ['status', 'reversal_payment_id', 'reversal_reason', 'updated_at']) !== []) {
                throw new \LogicException('Posted expense payments are immutable.');
            }
        });
        static::deleting(fn () => throw new \LogicException('Expense payments cannot be deleted.'));
    }

    /** @return BelongsTo<Organisation, $this> */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    /** @return BelongsTo<ExpenseRequest, $this> */
    public function expenseRequest(): BelongsTo
    {
        return $this->belongsTo(ExpenseRequest::class);
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

    /** @return BelongsTo<User, $this> */
    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by_user_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['payment_type' => ExpensePaymentType::class, 'payment_method' => ExpensePaymentMethod::class, 'status' => ExpensePaymentStatus::class, 'payment_date' => 'date'];
    }
}
