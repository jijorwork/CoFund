<?php

namespace App\Domain\Collections\Services;

use App\Domain\Collections\Enums\ContributionPaymentMethod;
use App\Domain\Collections\Exceptions\InvalidContributionPayment;
use App\Domain\Identity\Enums\SystemRole;
use App\Domain\Membership\Models\Member;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Domain\Wallets\Actions\CreateMemberWallet;
use App\Domain\Wallets\Enums\WalletOwnerType;
use App\Domain\Wallets\Enums\WalletStatus;
use App\Domain\Wallets\Enums\WalletType;
use App\Domain\Wallets\Models\Wallet;
use App\Models\User;

class ResolveContributionPaymentSource
{
    public function __construct(
        private TenantContext $context,
        private CreateMemberWallet $createMemberWallet,
    ) {}

    /** @return array{source: Wallet, destination: Wallet} */
    public function resolve(User $actor, Member $member, ContributionPaymentMethod $method, Wallet $source): array
    {
        $organisation = $this->context->organisation();
        $expectedType = match ($method) {
            ContributionPaymentMethod::Cash => $source->wallet_type,
            ContributionPaymentMethod::BankTransfer, ContributionPaymentMethod::Cheque,
            ContributionPaymentMethod::CardManual => WalletType::Bank,
            ContributionPaymentMethod::MobileMoneyManual => WalletType::MobileMoney,
            ContributionPaymentMethod::Adjustment => throw new InvalidContributionPayment('Adjustment payments are not exposed through collection.'),
        };
        $allowedCashType = in_array($source->wallet_type, [WalletType::OfficeCash, WalletType::CollectorCash], true);
        $collector = $this->context->role()->name === SystemRole::Collector->value;

        if (
            $source->organisation_id !== $organisation->getKey()
            || $source->status !== WalletStatus::Active
            || $source->wallet_type !== $expectedType
            || ($method === ContributionPaymentMethod::Cash && ! $allowedCashType)
            || ($this->context->branch() !== null && $source->branch_id !== $this->context->branch()->getKey())
            || ($collector && (
                $method !== ContributionPaymentMethod::Cash
                || $source->wallet_type !== WalletType::CollectorCash
                || $source->walletable_type !== WalletOwnerType::CollectorUser->value
                || $source->walletable_id !== $actor->getKey()
            ))
        ) {
            throw new InvalidContributionPayment('The payment source wallet is not authorised.');
        }

        $destination = Wallet::query()
            ->whereBelongsTo($organisation)
            ->where('walletable_type', WalletOwnerType::Member->value)
            ->where('walletable_id', $member->getKey())
            ->where('wallet_type', WalletType::MemberSavings)
            ->where('status', WalletStatus::Active)
            ->first();

        $destination ??= $this->createMemberWallet->handle(
            $actor,
            $member,
            WalletType::MemberSavings,
            'Member Savings',
            'SAV-'.$member->membership_number,
        );

        if ($destination->currency_code !== $source->currency_code) {
            throw new InvalidContributionPayment('The source and destination currencies must match.');
        }

        return ['source' => $source->load('ledgerAccount'), 'destination' => $destination->load('ledgerAccount')];
    }
}
