<?php

namespace App\Domain\Withdrawals\Services;

use App\Domain\Membership\Models\Member;
use App\Domain\Wallets\Enums\WalletStatus;
use App\Domain\Wallets\Enums\WalletType;
use App\Domain\Wallets\Models\Wallet;
use App\Domain\Wallets\Services\CalculateWalletBalance;
use App\Domain\Withdrawals\Models\SavingsHold;
use App\Domain\Withdrawals\Models\WithdrawalProduct;
use App\Domain\Withdrawals\Models\WithdrawalReason;
use App\Domain\Withdrawals\Models\WithdrawalRequest;

class CalculateWithdrawableSavings
{
    public function __construct(private CalculateWalletBalance $balances, private CalculateLoanWithdrawalRestrictions $loans, private CalculateGuarantorWithdrawalRestrictions $guarantors, private CalculateWelfareWithdrawalRestrictions $welfare, private CalculateContributionWithdrawalRestrictions $contributions) {}

    /** @return array<string, mixed> */
    public function calculate(Member $member, WithdrawalProduct $product, Wallet $wallet, ?WithdrawalReason $reason = null, ?WithdrawalRequest $excludingRequest = null): array
    {
        $valid = $wallet->organisation_id === $member->organisation_id && $product->organisation_id === $member->organisation_id && $wallet->walletable_type === 'member' && $wallet->walletable_id === $member->getKey() && $wallet->wallet_type === WalletType::MemberSavings && $wallet->status === WalletStatus::Active && $wallet->currency_code === $product->currency_code;
        $total = $valid ? max(0, $this->balances->calculate($wallet)->displayBalanceMinor) : 0;
        $holdsQuery = SavingsHold::query()->where('organisation_id', $member->organisation_id)->where('member_id', $member->getKey())->where('wallet_id', $wallet->getKey())->where('status', 'active')->where('starts_at', '<=', now())->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()));
        if ($excludingRequest?->reservation_hold_id !== null) {
            $holdsQuery->where('id', '!=', $excludingRequest->reservation_hold_id);
        }
        $holds = (int) (clone $holdsQuery)->where('hold_type', '!=', 'pending_withdrawal')->sum('amount_minor');
        $pending = (int) (clone $holdsQuery)->where('hold_type', 'pending_withdrawal')->sum('amount_minor');
        $loan = $this->loans->calculate($member, $product->loan_restriction_policy);
        $guarantor = $this->guarantors->calculate($member, $product->guarantor_restriction_policy);
        $welfare = $this->welfare->calculate($member, $product->welfare_restriction_policy);
        $contribution = $this->contributions->calculate($member, $product, $total);
        $percentageMinimum = intdiv($total * (int) ($product->minimum_remaining_balance_basis_points ?? 0), 10000);
        $minimum = max((int) ($product->minimum_remaining_balance_minor ?? 0), $percentageMinimum);
        $percentageLimit = $product->maximum_withdrawal_percentage_basis_points === null ? PHP_INT_MAX : intdiv($total * $product->maximum_withdrawal_percentage_basis_points, 10000);
        $productLimit = min((int) ($product->maximum_withdrawal_minor ?? PHP_INT_MAX), $percentageLimit);
        $reasonLimit = $reason === null
            ? PHP_INT_MAX
            : min(
                (int) ($reason->maximum_withdrawal_minor ?? PHP_INT_MAX),
                $reason->maximum_withdrawal_percentage_basis_points === null
                    ? PHP_INT_MAX
                    : intdiv($total * $reason->maximum_withdrawal_percentage_basis_points, 10000),
            );
        $base = max(0, $total - $holds - $pending - (int) $loan['restricted_amount_minor'] - (int) $guarantor['restricted_amount_minor'] - (int) $welfare['restricted_amount_minor'] - $minimum);
        $blocked = array_merge($valid ? [] : ['ineligible_savings_wallet'], $loan['blocking_reasons'], $guarantor['blocking_reasons'], $welfare['blocking_reasons'], $contribution['blocking_reasons']);
        $withdrawable = $blocked === [] ? max(0, min($base, $productLimit, $reasonLimit)) : 0;

        return ['total_savings_balance_minor' => $total, 'eligible_savings_balance_minor' => $total, 'active_holds_minor' => $holds, 'loan_security_minor' => $loan['restricted_amount_minor'], 'guarantor_security_minor' => $guarantor['restricted_amount_minor'], 'welfare_restriction_minor' => $welfare['restricted_amount_minor'], 'pending_withdrawal_reservations_minor' => $pending, 'minimum_required_balance_minor' => $minimum, 'product_limit_minor' => $productLimit === PHP_INT_MAX ? $total : $productLimit, 'period_limit_remaining_minor' => $total, 'reason_limit_minor' => $reasonLimit === PHP_INT_MAX ? $total : $reasonLimit, 'withdrawable_balance_minor' => $withdrawable, 'blocking_reasons' => $blocked, 'warnings' => array_merge($loan['warnings'], $guarantor['warnings'], $welfare['warnings'], $contribution['warnings']), 'calculation_breakdown' => ['loan' => $loan, 'guarantor' => $guarantor, 'welfare' => $welfare, 'contribution' => $contribution]];
    }
}
