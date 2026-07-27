<?php

namespace App\Domain\Wallets\Actions;

use App\Domain\Organisations\Tenancy\TenantContext;
use App\Domain\Wallets\Enums\WalletOwnerType;
use App\Domain\Wallets\Enums\WalletType;
use App\Domain\Wallets\Exceptions\InvalidWalletOperation;
use App\Domain\Wallets\Models\Wallet;
use App\Domain\Wallets\Services\CreateWallet;
use App\Domain\Wallets\Services\SystemWalletAccountResolver;
use App\Models\User;

class CreateOrganisationWallet
{
    public function __construct(
        private TenantContext $context,
        private SystemWalletAccountResolver $accounts,
        private CreateWallet $wallets,
    ) {}

    public function handle(User $actor, WalletType $type, string $name, string $code): Wallet
    {
        if (! in_array($type, [
            WalletType::OfficeCash,
            WalletType::Bank,
            WalletType::MobileMoney,
            WalletType::Suspense,
        ], true)) {
            throw new InvalidWalletOperation;
        }

        $organisation = $this->context->organisation();

        return $this->wallets->handle(
            $organisation,
            null,
            $organisation,
            WalletOwnerType::Organisation,
            $type,
            $name,
            $code,
            $this->accounts->resolve($organisation, $type),
            $actor,
            isSystemWallet: true,
        );
    }
}
