<?php

namespace App\Domain\Wallets\Actions;

use App\Domain\Accounting\Services\AccountingScope;
use App\Domain\Organisations\Models\Branch;
use App\Domain\Organisations\Models\OrganisationUser;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Domain\Wallets\Enums\WalletOwnerType;
use App\Domain\Wallets\Enums\WalletType;
use App\Domain\Wallets\Exceptions\InvalidWalletOperation;
use App\Domain\Wallets\Models\Wallet;
use App\Domain\Wallets\Services\CreateWallet;
use App\Domain\Wallets\Services\SystemWalletAccountResolver;
use App\Models\User;

class CreateCollectorWallet
{
    public function __construct(
        private TenantContext $context,
        private AccountingScope $scope,
        private SystemWalletAccountResolver $accounts,
        private CreateWallet $wallets,
    ) {}

    public function handle(User $actor, User $collector, Branch $branch, string $name, string $code): Wallet
    {
        $organisation = $this->context->organisation();
        $this->scope->assertBranch($branch);

        if (! OrganisationUser::query()
            ->whereBelongsTo($organisation)
            ->whereBelongsTo($collector)
            ->where('status', 'active')
            ->exists()) {
            throw new InvalidWalletOperation;
        }

        return $this->wallets->handle(
            $organisation,
            $branch,
            $collector,
            WalletOwnerType::CollectorUser,
            WalletType::CollectorCash,
            $name,
            $code,
            $this->accounts->resolve($organisation, WalletType::CollectorCash),
            $actor,
        );
    }
}
