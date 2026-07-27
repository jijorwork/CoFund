<?php

namespace App\Domain\Wallets\Actions;

use App\Domain\Accounting\Services\AccountingScope;
use App\Domain\Organisations\Models\Branch;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Domain\Wallets\Enums\WalletOwnerType;
use App\Domain\Wallets\Enums\WalletType;
use App\Domain\Wallets\Exceptions\InvalidWalletOperation;
use App\Domain\Wallets\Models\Wallet;
use App\Domain\Wallets\Services\CreateWallet;
use App\Domain\Wallets\Services\SystemWalletAccountResolver;
use App\Models\User;

class CreateBranchWallet
{
    public function __construct(
        private TenantContext $context,
        private AccountingScope $scope,
        private SystemWalletAccountResolver $accounts,
        private CreateWallet $wallets,
    ) {}

    public function handle(User $actor, Branch $branch, WalletType $type, string $name, string $code): Wallet
    {
        if (! in_array($type, [WalletType::OfficeCash, WalletType::Bank], true)) {
            throw new InvalidWalletOperation;
        }

        $this->scope->assertBranch($branch);
        $organisation = $this->context->organisation();

        return $this->wallets->handle(
            $organisation,
            $branch,
            $branch,
            WalletOwnerType::Branch,
            $type,
            $name,
            $code,
            $this->accounts->resolve($organisation, $type),
            $actor,
        );
    }
}
