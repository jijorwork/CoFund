<?php

namespace App\Domain\Withdrawals\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['public_id', 'organisation_id', 'withdrawal_request_id', 'currency_code', 'requested_amount_minor', 'eligible_amount_minor', 'approved_amount_minor', 'fee_type', 'fee_value', 'fee_minor', 'net_payout_minor', 'minimum_remaining_balance_minor', 'active_holds_minor', 'loan_restriction_minor', 'guarantor_restriction_minor', 'welfare_restriction_minor', 'pending_withdrawal_reservations_minor', 'notice_period_end_date', 'required_payout_date', 'eligibility_snapshot', 'restriction_snapshot', 'calculation_snapshot'])]
class WithdrawalRequestTerm extends Model
{
    use HasUuids;

    protected static function booted(): void
    {
        static::updating(function (self $terms): void {
            if ($terms->getOriginal('approved_amount_minor') !== null || array_diff(array_keys($terms->getDirty()), ['approved_amount_minor', 'fee_minor', 'net_payout_minor', 'updated_at']) !== []) {
                throw new \LogicException('Approved withdrawal terms are immutable.');
            }
        });
        static::deleting(fn (): never => throw new \LogicException('Withdrawal terms cannot be deleted.'));
    }

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['notice_period_end_date' => 'date', 'required_payout_date' => 'date', 'eligibility_snapshot' => 'array', 'restriction_snapshot' => 'array', 'calculation_snapshot' => 'array'];
    }
}
