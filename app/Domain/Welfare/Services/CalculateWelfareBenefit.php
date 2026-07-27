<?php

namespace App\Domain\Welfare\Services;

use App\Domain\Collections\Enums\ContributionPaymentStatus;
use App\Domain\Collections\Models\ContributionPayment;
use App\Domain\Wallets\Enums\WalletType;
use App\Domain\Wallets\Services\CalculateMemberWalletBalances;
use App\Domain\Welfare\Exceptions\InvalidWelfareOperation;
use App\Domain\Welfare\Models\WelfareClaim;

class CalculateWelfareBenefit
{
    public function __construct(private CalculateMemberWalletBalances $walletBalances) {}

    /** @return array{base_amount_minor:int,rate_basis_points:int,raw_calculated_benefit_minor:int,minimum_applied:bool,maximum_applied:bool,eligible_benefit_minor:int,explanation:string} */
    public function calculate(WelfareClaim $claim, ?int $declaredCostMinor = null): array
    {
        $product = $claim->product;
        $base = match ($product->benefit_calculation_type) {
            'fixed' => (int) $product->fixed_benefit_minor,
            'percentage_of_contributions' => (int) ContributionPayment::query()->where('organisation_id', $claim->organisation_id)->where('member_id', $claim->member_id)->whereIn('status', [ContributionPaymentStatus::Posted->value, ContributionPaymentStatus::Allocated->value, ContributionPaymentStatus::PartiallyAllocated->value])->sum('amount_minor'),
            'percentage_of_savings' => collect($this->walletBalances->calculate($claim->member))->filter(fn (array $row): bool => $row['wallet']->wallet_type === WalletType::MemberSavings)->sum(fn (array $row): int => max(0, $row['balance']->displayBalanceMinor)),
            'percentage_of_declared_cost', 'manual_with_limits' => $declaredCostMinor ?? $claim->requested_benefit_minor,
            default => throw new InvalidWelfareOperation('Unsupported welfare benefit calculation type.'),
        };
        $rate = $product->benefit_calculation_type === 'fixed' ? 10000 : (int) $product->benefit_percentage_basis_points;
        $raw = $product->benefit_calculation_type === 'fixed' ? $base : intdiv($base * $rate, 10000);
        $minimumApplied = $product->minimum_benefit_minor !== null && $raw < $product->minimum_benefit_minor;
        $maximumApplied = $product->maximum_benefit_minor !== null && $raw > $product->maximum_benefit_minor;
        $eligible = max((int) ($product->minimum_benefit_minor ?? 0), $raw);
        if ($product->maximum_benefit_minor !== null) {
            $eligible = min($eligible, $product->maximum_benefit_minor);
        }

        return ['base_amount_minor' => $base, 'rate_basis_points' => $rate, 'raw_calculated_benefit_minor' => $raw, 'minimum_applied' => $minimumApplied, 'maximum_applied' => $maximumApplied, 'eligible_benefit_minor' => $eligible, 'explanation' => $product->benefit_calculation_type.' using integer basis-point arithmetic'];
    }
}
