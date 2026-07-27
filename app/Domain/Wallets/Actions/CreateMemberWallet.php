<?php

namespace App\Domain\Wallets\Actions;

use App\Domain\Accounting\Services\AccountingScope;
use App\Domain\Membership\Enums\MemberStatus;
use App\Domain\Membership\Models\Member;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Domain\Wallets\Enums\WalletOwnerType;
use App\Domain\Wallets\Enums\WalletType;
use App\Domain\Wallets\Exceptions\InvalidWalletOperation;
use App\Domain\Wallets\Models\Wallet;
use App\Domain\Wallets\Services\CreateWallet;
use App\Domain\Wallets\Services\SystemWalletAccountResolver;
use App\Models\User;

class CreateMemberWallet
{
    public function __construct(
        private TenantContext $context,
        private AccountingScope $scope,
        private SystemWalletAccountResolver $accounts,
        private CreateWallet $wallets,
    ) {}

    public function handle(User $actor, Member $member, WalletType $type, string $name, string $code): Wallet
    {
        $organisation = $this->context->organisation();

        if (
            ! in_array($type, [WalletType::MemberSavings, WalletType::MemberLoan], true)
            || $member->status !== MemberStatus::Active
            || $member->organisation_id !== $organisation->getKey()
        ) {
            throw new InvalidWalletOperation;
        }

        $this->scope->assertBranch($member->branch);

        return $this->wallets->handle(
            $organisation,
            $member->branch,
            $member,
            WalletOwnerType::Member,
            $type,
            $name,
            $code,
            $this->accounts->resolve($organisation, $type),
            $actor,
        );
    }
}
