<?php

namespace App\Domain\Withdrawals\Services;

use App\Domain\Withdrawals\Exceptions\InvalidWithdrawalOperation;
use App\Domain\Withdrawals\Models\WithdrawalProduct;

class CalculateWithdrawalFee
{
    /** @return array{fee_type:string,fee_base_minor:int,fee_rate_basis_points:int|null,fee_minor:int,gross_withdrawal_minor:int,net_payout_minor:int,calculation_explanation:string} */
    public function calculate(WithdrawalProduct $product, int $grossMinor): array
    {
        if ($grossMinor <= 0) {
            throw new InvalidWithdrawalOperation('Gross withdrawal must be positive.');
        }
        $fee = match ($product->fee_type) {
            'none' => 0,
            'fixed' => (int) $product->fee_value,
            'percentage' => intdiv($grossMinor * (int) $product->fee_value, 10000),
            default => throw new InvalidWithdrawalOperation('Unsupported withdrawal fee type.'),
        };
        if ($product->fee_treatment !== 'deducted_from_payout') {
            throw new InvalidWithdrawalOperation('Only deducted withdrawal fees are supported in the MVP.');
        }
        if ($fee > $grossMinor) {
            throw new InvalidWithdrawalOperation('Withdrawal fee cannot exceed the gross withdrawal.');
        }

        return ['fee_type' => $product->fee_type, 'fee_base_minor' => $grossMinor, 'fee_rate_basis_points' => $product->fee_type === 'percentage' ? (int) $product->fee_value : null, 'fee_minor' => $fee, 'gross_withdrawal_minor' => $grossMinor, 'net_payout_minor' => $grossMinor - $fee, 'calculation_explanation' => 'Fee calculated from gross withdrawal using integer minor units and basis points.'];
    }
}
