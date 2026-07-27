<?php

namespace App\Domain\Withdrawals\Actions;

use App\Domain\Accounting\Actions\ReverseFinancialTransaction;
use App\Domain\Collections\Enums\ReceiptStatus;
use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Withdrawals\Enums\SavingsHoldStatus;
use App\Domain\Withdrawals\Enums\WithdrawalPayoutStatus;
use App\Domain\Withdrawals\Enums\WithdrawalRequestStatus;
use App\Domain\Withdrawals\Exceptions\InvalidWithdrawalOperation;
use App\Domain\Withdrawals\Models\WithdrawalPayout;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReverseWithdrawalPayout
{
    public function __construct(private RoleAuthorization $authorization, private ReverseFinancialTransaction $reversals) {}

    public function handle(User $actor, WithdrawalPayout $payout, string $reason, string $idempotencyKey): WithdrawalPayout
    {
        $this->authorization->authorize(FoundationPermission::WithdrawalPayoutsReverse->value);
        if (trim($reason) === '') {
            throw new InvalidWithdrawalOperation('A payout reversal reason is required.');
        }

        return DB::transaction(function () use ($actor, $payout, $reason, $idempotencyKey): WithdrawalPayout {
            $locked = WithdrawalPayout::query()->with(['financialTransaction', 'receipt', 'request.reservationHold'])->lockForUpdate()->whereKey($payout->getKey())->firstOrFail();
            if ($locked->status === WithdrawalPayoutStatus::Reversed) {
                return $locked;
            }
            $reversal = $this->reversals->handle($locked->financialTransaction, $actor, $reason, 'withdrawal-payout-reversal:'.$idempotencyKey, FoundationPermission::WithdrawalPayoutsReverse->value);
            $locked->update(['status' => WithdrawalPayoutStatus::Reversed, 'reversal_transaction_id' => $reversal->getKey(), 'reversal_reason' => $reason, 'reversed_at' => now()]);
            $locked->receipt?->update(['status' => ReceiptStatus::Reversed]);
            $request = $locked->request;
            $outstanding = (int) $request->outstanding_approved_minor + (int) $locked->gross_withdrawal_minor;
            $request->reservationHold?->update(['amount_minor' => $outstanding, 'status' => SavingsHoldStatus::Active, 'released_at' => null, 'released_by_user_id' => null, 'release_reason' => null]);
            $request->update(['total_paid_minor' => max(0, (int) $request->total_paid_minor - (int) $locked->gross_withdrawal_minor), 'outstanding_approved_minor' => $outstanding, 'payment_status' => $request->total_paid_minor > $locked->gross_withdrawal_minor ? 'partially_paid' : 'unpaid', 'status' => $request->total_paid_minor > $locked->gross_withdrawal_minor ? WithdrawalRequestStatus::PartiallyPaid : WithdrawalRequestStatus::Approved, 'settled_at' => null]);

            return $locked->refresh();
        }, attempts: 3);
    }
}
