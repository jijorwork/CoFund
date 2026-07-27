<?php

namespace App\Domain\Welfare\Actions;

use App\Domain\Accounting\Actions\ReverseFinancialTransaction;
use App\Domain\Collections\Enums\ReceiptStatus;
use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Welfare\Enums\WelfareBenefitPaymentStatus;
use App\Domain\Welfare\Enums\WelfareClaimStatus;
use App\Domain\Welfare\Exceptions\InvalidWelfareOperation;
use App\Domain\Welfare\Models\WelfareBenefitPayment;
use App\Domain\Welfare\Services\RecalculateWelfareClaimPosition;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReverseWelfareBenefitPayment
{
    public function __construct(private RoleAuthorization $authorization, private ReverseFinancialTransaction $reversals, private RecalculateWelfareClaimPosition $positions) {}

    public function handle(User $actor, WelfareBenefitPayment $payment, string $reason, string $idempotencyKey): WelfareBenefitPayment
    {
        $this->authorization->authorize(FoundationPermission::WelfarePaymentsReverse->value);
        if (trim($reason) === '') {
            throw new InvalidWelfareOperation('A reversal reason is required.');
        }

        return DB::transaction(function () use ($actor, $payment, $reason, $idempotencyKey): WelfareBenefitPayment {
            $locked = WelfareBenefitPayment::query()->with(['financialTransaction', 'receipt', 'claim'])->lockForUpdate()->whereKey($payment->getKey())->firstOrFail();
            if ($locked->status === WelfareBenefitPaymentStatus::Reversed) {
                return $locked;
            }
            $reversal = $this->reversals->handle($locked->financialTransaction, $actor, $reason, 'welfare-payment-reversal:'.$idempotencyKey, FoundationPermission::WelfarePaymentsReverse->value);
            $locked->update(['status' => WelfareBenefitPaymentStatus::Reversed, 'reversal_transaction_id' => $reversal->getKey(), 'reversal_reason' => $reason, 'reversed_at' => now()]);
            $locked->receipt?->update(['status' => ReceiptStatus::Reversed]);
            if ($locked->claim->status === WelfareClaimStatus::Settled) {
                $locked->claim->update(['settled_at' => null]);
            }
            $this->positions->recalculate($locked->claim);

            return $locked->refresh();
        }, attempts: 3);
    }
}
