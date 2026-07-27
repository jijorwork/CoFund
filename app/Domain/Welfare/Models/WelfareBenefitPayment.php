<?php

namespace App\Domain\Welfare\Models;

use App\Domain\Accounting\Models\FinancialTransaction;
use App\Domain\Collections\Models\Receipt;
use App\Domain\Wallets\Models\Wallet;
use App\Domain\Welfare\Enums\WelfareBenefitPaymentStatus;
use Database\Factories\Domain\Welfare\Models\WelfareBenefitPaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property WelfareBenefitPaymentStatus $status
 * @property Carbon $payment_date
 */
#[Fillable(['public_id', 'organisation_id', 'branch_id', 'welfare_claim_id', 'welfare_claim_liability_id', 'welfare_claim_beneficiary_id', 'payment_number', 'source_wallet_id', 'recipient_type', 'recipient_name', 'recipient_reference', 'amount_minor', 'currency_code', 'payment_method', 'payment_date', 'status', 'financial_transaction_id', 'receipt_id', 'external_reference', 'paid_by_user_id', 'approved_by_user_id', 'idempotency_key', 'request_fingerprint', 'reversal_transaction_id', 'reversal_reason', 'reversed_at'])]
class WelfareBenefitPayment extends Model
{
    /** @use HasFactory<WelfareBenefitPaymentFactory> */
    use HasFactory, HasUuids;

    protected $attributes = ['status' => 'posted'];

    protected static function booted(): void
    {
        static::deleting(fn (): never => throw new \LogicException('Welfare payments cannot be deleted.'));
    }

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    /** @return BelongsTo<WelfareClaim,$this> */
    public function claim(): BelongsTo
    {
        return $this->belongsTo(WelfareClaim::class, 'welfare_claim_id');
    }

    /** @return BelongsTo<WelfareClaimLiability,$this> */
    public function liability(): BelongsTo
    {
        return $this->belongsTo(WelfareClaimLiability::class, 'welfare_claim_liability_id');
    }

    /** @return BelongsTo<WelfareClaimBeneficiary,$this> */
    public function beneficiary(): BelongsTo
    {
        return $this->belongsTo(WelfareClaimBeneficiary::class, 'welfare_claim_beneficiary_id');
    }

    /** @return BelongsTo<Wallet,$this> */
    public function sourceWallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'source_wallet_id');
    }

    /** @return BelongsTo<FinancialTransaction,$this> */
    public function financialTransaction(): BelongsTo
    {
        return $this->belongsTo(FinancialTransaction::class);
    }

    /** @return BelongsTo<Receipt,$this> */
    public function receipt(): BelongsTo
    {
        return $this->belongsTo(Receipt::class);
    }

    /** @return array<string,string> */
    protected function casts(): array
    {
        return ['status' => WelfareBenefitPaymentStatus::class, 'payment_date' => 'date', 'reversed_at' => 'datetime'];
    }
}
