<?php

namespace App\Domain\Loans\Models;

use App\Domain\Accounting\Models\FinancialTransaction;
use App\Domain\Wallets\Models\Wallet;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon $disbursement_date
 * @property Carbon|null $reversed_at
 */
#[Fillable(['public_id', 'organisation_id', 'branch_id', 'loan_id', 'source_wallet_id', 'member_loan_wallet_id', 'financial_transaction_id', 'disbursement_number', 'destination_type', 'destination_reference', 'gross_principal_minor', 'deducted_fee_minor', 'financed_fee_minor', 'net_disbursed_minor', 'currency_code', 'method', 'disbursement_date', 'status', 'idempotency_key', 'request_fingerprint', 'disbursed_by_user_id', 'reversal_transaction_id', 'reversal_reason', 'reversed_at'])]
class LoanDisbursement extends Model
{
    use HasUuids;

    protected static function booted(): void
    {
        static::deleting(fn (): never => throw new \LogicException('Loan disbursements cannot be deleted.'));
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

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['disbursement_date' => 'date', 'reversed_at' => 'datetime'];
    }
}
