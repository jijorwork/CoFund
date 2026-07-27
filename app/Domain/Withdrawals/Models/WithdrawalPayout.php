<?php

namespace App\Domain\Withdrawals\Models;

use App\Domain\Accounting\Models\FinancialTransaction;
use App\Domain\Collections\Models\Receipt;
use App\Domain\Wallets\Models\Wallet;
use App\Domain\Withdrawals\Enums\WithdrawalPayoutStatus;
use Database\Factories\Domain\Withdrawals\Models\WithdrawalPayoutFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property WithdrawalPayoutStatus $status
 * @property Carbon $payout_date
 */
#[Fillable(['public_id', 'organisation_id', 'branch_id', 'withdrawal_request_id', 'payout_number', 'member_savings_wallet_id', 'destination_wallet_id', 'recipient_type', 'recipient_name', 'recipient_reference', 'gross_withdrawal_minor', 'fee_minor', 'net_payout_minor', 'currency_code', 'payment_method', 'payout_date', 'status', 'financial_transaction_id', 'receipt_id', 'external_reference', 'paid_by_user_id', 'approved_by_user_id', 'idempotency_key', 'request_fingerprint', 'reversal_transaction_id', 'reversal_reason', 'reversed_at'])]
class WithdrawalPayout extends Model
{
    /** @use HasFactory<WithdrawalPayoutFactory> */
    use HasFactory, HasUuids;

    protected $attributes = ['status' => 'posted'];

    protected static function booted(): void
    {
        static::deleting(fn (): never => throw new \LogicException('Withdrawal payouts cannot be deleted.'));
    }

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    /** @return BelongsTo<WithdrawalRequest, $this> */
    public function request(): BelongsTo
    {
        return $this->belongsTo(WithdrawalRequest::class, 'withdrawal_request_id');
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

    /** @return BelongsTo<Receipt, $this> */
    public function receipt(): BelongsTo
    {
        return $this->belongsTo(Receipt::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['status' => WithdrawalPayoutStatus::class, 'payout_date' => 'date', 'reversed_at' => 'datetime'];
    }
}
