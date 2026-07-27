<?php

namespace App\Domain\Welfare\Services;

use App\Domain\Welfare\Enums\WelfareBenefitPaymentStatus;
use App\Domain\Welfare\Enums\WelfareClaimStatus;
use App\Domain\Welfare\Enums\WelfareLiabilityStatus;
use App\Domain\Welfare\Enums\WelfarePaymentStatus;
use App\Domain\Welfare\Models\WelfareClaim;

class RecalculateWelfareClaimPosition
{
    /** @return array<string,mixed> */
    public function recalculate(WelfareClaim $claim): array
    {
        $recognised = (int) $claim->liabilities()->where('status', WelfareLiabilityStatus::Recognised)->sum('approved_benefit_minor');
        $paid = (int) $claim->payments()->where('status', WelfareBenefitPaymentStatus::Posted)->sum('amount_minor');
        $reversed = (int) $claim->payments()->where('status', WelfareBenefitPaymentStatus::Reversed)->sum('amount_minor');
        $outstanding = max(0, $recognised - $paid);
        $paymentStatus = $recognised === 0 ? WelfarePaymentStatus::NotPayable : ($outstanding === 0 ? WelfarePaymentStatus::Paid : ($paid > 0 ? WelfarePaymentStatus::PartiallyPaid : WelfarePaymentStatus::Unpaid));
        $status = $claim->status;
        if ($recognised > 0) {
            $status = $outstanding === 0 ? WelfareClaimStatus::Paid : ($paid > 0 ? WelfareClaimStatus::PartiallyPaid : WelfareClaimStatus::Approved);
        }
        $claim->update(['total_paid_minor' => $paid, 'outstanding_payable_minor' => $outstanding, 'payment_status' => $paymentStatus, 'status' => $status]);

        return ['requested_benefit_minor' => $claim->requested_benefit_minor, 'eligible_benefit_minor' => $claim->eligible_benefit_minor, 'approved_benefit_minor' => $claim->approved_benefit_minor, 'recognised_liability_minor' => $recognised, 'total_active_payments_minor' => $paid, 'total_reversed_payments_minor' => $reversed, 'outstanding_payable_minor' => $outstanding, 'claim_status' => $status->value, 'payment_status' => $paymentStatus->value, 'settlement_status' => $claim->settled_at === null ? 'open' : 'settled', 'next_required_action' => $recognised === 0 ? 'recognise_liability' : ($outstanding > 0 ? 'pay_benefit' : 'settle')];
    }
}
