<?php

namespace App\Domain\Loans\Services;

use App\Domain\Loans\Enums\InterestMethod;
use App\Domain\Loans\Enums\ProcessingFeeTreatment;
use App\Domain\Loans\Enums\ProcessingFeeType;
use App\Domain\Loans\Models\LoanProduct;

class CalculateLoanTerms
{
    /** @return array{principal_minor:int, contractual_interest_minor:int, processing_fee_minor:int, financed_fee_minor:int, deducted_fee_minor:int, financed_principal_minor:int, net_disbursed_minor:int, total_repayable_minor:int} */
    public function calculate(LoanProduct $product, int $principalMinor, int $term): array
    {
        $fee = match ($product->processing_fee_type) {
            ProcessingFeeType::None => 0,
            ProcessingFeeType::Fixed => $product->processing_fee_fixed_minor,
            ProcessingFeeType::Percentage => intdiv($principalMinor * $product->processing_fee_basis_points + 9999, 10000),
        };
        $interest = match ($product->interest_method) {
            InterestMethod::ZeroInterest => 0,
            InterestMethod::Flat => intdiv($principalMinor * $product->interest_rate_basis_points * $term + 9999, 10000),
            InterestMethod::ReducingBalance => $this->reducingInterest($principalMinor, $product->interest_rate_basis_points, $term),
        };
        $financedFee = $product->processing_fee_treatment === ProcessingFeeTreatment::AddedToLoan ? $fee : 0;
        $deductedFee = $product->processing_fee_treatment === ProcessingFeeTreatment::Deducted ? $fee : 0;
        $financedPrincipal = $principalMinor + $financedFee;

        return [
            'principal_minor' => $principalMinor,
            'contractual_interest_minor' => $interest,
            'processing_fee_minor' => $fee,
            'financed_fee_minor' => $financedFee,
            'deducted_fee_minor' => $deductedFee,
            'financed_principal_minor' => $financedPrincipal,
            'net_disbursed_minor' => $principalMinor - $deductedFee,
            'total_repayable_minor' => $financedPrincipal + $interest,
        ];
    }

    private function reducingInterest(int $principal, int $rateBasisPoints, int $term): int
    {
        $principalPart = intdiv($principal, $term);
        $remaining = $principal;
        $interest = 0;

        for ($period = 1; $period <= $term; $period++) {
            $interest += intdiv($remaining * $rateBasisPoints + 9999, 10000);
            $remaining -= $period === $term ? $remaining : $principalPart;
        }

        return $interest;
    }
}
