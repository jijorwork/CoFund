<?php

namespace App\Domain\Welfare\Actions;

use App\Domain\Accounting\Actions\ReverseFinancialTransaction;
use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Welfare\Enums\WelfareBenefitPaymentStatus;
use App\Domain\Welfare\Enums\WelfareClaimStatus;
use App\Domain\Welfare\Enums\WelfareLiabilityStatus;
use App\Domain\Welfare\Enums\WelfarePaymentStatus;
use App\Domain\Welfare\Exceptions\InvalidWelfareOperation;
use App\Domain\Welfare\Models\WelfareClaimLiability;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReverseWelfareClaimLiability
{
    public function __construct(private RoleAuthorization $authorization, private ReverseFinancialTransaction $reversals) {}

    public function handle(User $actor, WelfareClaimLiability $liability, string $reason, string $idempotencyKey): WelfareClaimLiability
    {
        $this->authorization->authorize(FoundationPermission::WelfareLiabilitiesReverse->value);
        if (trim($reason) === '') {
            throw new InvalidWelfareOperation('A reversal reason is required.');
        }

        return DB::transaction(function () use ($actor, $liability, $reason, $idempotencyKey): WelfareClaimLiability {
            $locked = WelfareClaimLiability::query()->with(['financialTransaction', 'claim', 'payments'])->lockForUpdate()->whereKey($liability->getKey())->firstOrFail();
            if ($locked->status === WelfareLiabilityStatus::Reversed) {
                return $locked;
            }
            if ($locked->payments->contains(fn ($payment): bool => $payment->status === WelfareBenefitPaymentStatus::Posted)) {
                throw new InvalidWelfareOperation('Active welfare payments must be reversed first.');
            }
            $reversal = $this->reversals->handle($locked->financialTransaction, $actor, $reason, 'welfare-liability-reversal:'.$idempotencyKey, FoundationPermission::WelfareLiabilitiesReverse->value);
            $locked->update(['status' => WelfareLiabilityStatus::Reversed, 'active_liability_key' => null, 'reversal_transaction_id' => $reversal->getKey(), 'reversal_reason' => $reason, 'reversed_at' => now()]);
            $locked->claim->update(['status' => WelfareClaimStatus::Reversed, 'payment_status' => WelfarePaymentStatus::Reversed, 'outstanding_payable_minor' => 0, 'liability_transaction_id' => null]);

            return $locked->refresh();
        }, attempts: 3);
    }
}
