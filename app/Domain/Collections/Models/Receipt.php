<?php

namespace App\Domain\Collections\Models;

use App\Domain\Accounting\Models\FinancialTransaction;
use App\Domain\Collections\Enums\ReceiptStatus;
use App\Domain\Collections\Enums\ReceiptType;
use App\Domain\Loans\Models\LoanRepayment;
use App\Domain\Membership\Models\Member;
use App\Domain\Organisations\Models\Branch;
use App\Domain\Organisations\Models\Organisation;
use App\Domain\Welfare\Models\WelfareBenefitPayment;
use App\Models\User;
use Database\Factories\Domain\Collections\Models\ReceiptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property ReceiptType $receipt_type
 * @property ReceiptStatus $status
 * @property string $payment_method
 * @property Carbon $issued_at
 * @property array<string, mixed>|null $metadata
 */
#[Fillable([
    'public_id', 'organisation_id', 'branch_id', 'contribution_payment_id', 'loan_repayment_id', 'welfare_benefit_payment_id',
    'financial_transaction_id', 'receipt_number', 'receipt_type', 'status',
    'issued_at', 'issued_by_user_id', 'member_id', 'payer_name', 'payment_method',
    'amount_minor', 'currency_code', 'verification_code', 'verification_hash',
    'original_receipt_id', 'reversal_receipt_id', 'metadata',
])]
class Receipt extends Model
{
    /** @use HasFactory<ReceiptFactory> */
    use HasFactory, HasUuids;

    protected static function booted(): void
    {
        static::updating(function (self $receipt): void {
            if (array_diff(array_keys($receipt->getDirty()), ['status', 'reversal_receipt_id', 'updated_at']) !== []) {
                throw new \LogicException('Receipts are immutable.');
            }
        });
        static::deleting(fn (): never => throw new \LogicException('Receipts cannot be deleted.'));
    }

    protected static function newFactory(): ReceiptFactory
    {
        return ReceiptFactory::new();
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

    /** @return BelongsTo<ContributionPayment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(ContributionPayment::class, 'contribution_payment_id');
    }

    /** @return BelongsTo<LoanRepayment, $this> */
    public function loanRepayment(): BelongsTo
    {
        return $this->belongsTo(LoanRepayment::class);
    }

    /** @return BelongsTo<WelfareBenefitPayment, $this> */
    public function welfareBenefitPayment(): BelongsTo
    {
        return $this->belongsTo(WelfareBenefitPayment::class);
    }

    /** @return BelongsTo<FinancialTransaction, $this> */
    public function financialTransaction(): BelongsTo
    {
        return $this->belongsTo(FinancialTransaction::class);
    }

    /** @return BelongsTo<User, $this> */
    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by_user_id');
    }

    /** @return BelongsTo<Member, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /** @return BelongsTo<Receipt, $this> */
    public function originalReceipt(): BelongsTo
    {
        return $this->belongsTo(self::class, 'original_receipt_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'receipt_type' => ReceiptType::class,
            'status' => ReceiptStatus::class,
            'payment_method' => 'string',
            'issued_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
