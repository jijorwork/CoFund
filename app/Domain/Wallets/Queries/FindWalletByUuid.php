<?php

namespace App\Domain\Wallets\Queries;

use App\Domain\Accounting\Services\AccountingScope;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Domain\Wallets\Models\Wallet;

class FindWalletByUuid
{
    public function __construct(private TenantContext $context, private AccountingScope $scope) {}

    public function handle(string $uuid): ?Wallet
    {
        $query = Wallet::query()
            ->whereBelongsTo($this->context->organisation())
            ->with(['branch', 'ledgerAccount', 'walletable'])
            ->where('public_id', $uuid);
        $this->scope->apply($query);

        if ($this->context->role()->name === 'collector') {
            $query->where('walletable_type', 'collector_user')
                ->where('walletable_id', $this->context->roleAssignment()->user_id);
        }

        return $query->first();
    }
}
