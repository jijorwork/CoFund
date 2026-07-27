<?php

namespace App\Domain\Welfare\Models;

use App\Domain\Accounting\Models\FinancialTransaction;
use App\Domain\Welfare\Enums\WelfareLiabilityStatus;
use Database\Factories\Domain\Welfare\Models\WelfareClaimLiabilityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @property WelfareLiabilityStatus $status */
#[Fillable(['public_id', 'organisation_id', 'welfare_claim_id', 'liability_number', 'approved_benefit_minor', 'currency_code', 'status', 'active_liability_key', 'financial_transaction_id', 'recognised_at', 'recognised_by_user_id', 'idempotency_key', 'request_fingerprint', 'reversal_transaction_id', 'reversal_reason', 'reversed_at'])]
class WelfareClaimLiability extends Model
{
    /** @use HasFactory<WelfareClaimLiabilityFactory> */
    use HasFactory, HasUuids;

    protected $attributes = ['status' => 'recognised'];

    protected static function booted(): void
    {
        static::deleting(fn (): never => throw new \LogicException('Welfare liabilities cannot be deleted.'));
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

    /** @return BelongsTo<FinancialTransaction,$this> */
    public function financialTransaction(): BelongsTo
    {
        return $this->belongsTo(FinancialTransaction::class);
    }

    /** @return HasMany<WelfareBenefitPayment,$this> */
    public function payments(): HasMany
    {
        return $this->hasMany(WelfareBenefitPayment::class);
    }

    /** @return array<string,string> */
    protected function casts(): array
    {
        return ['status' => WelfareLiabilityStatus::class, 'recognised_at' => 'datetime', 'reversed_at' => 'datetime'];
    }
}
