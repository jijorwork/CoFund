<?php

namespace App\Domain\Accounting\Queries;

use App\Domain\Accounting\Models\LedgerAccount;
use App\Domain\Accounting\Services\AccountingScope;
use App\Domain\Organisations\Tenancy\TenantContext;

class FindLedgerAccountByUuid
{
    public function __construct(private TenantContext $context, private AccountingScope $scope) {}

    public function handle(string $uuid): ?LedgerAccount
    {
        $query = LedgerAccount::query()
            ->whereBelongsTo($this->context->organisation())
            ->with(['branch', 'parent'])
            ->where('public_id', $uuid);
        $this->scope->apply($query, includeOrganisationWide: true);

        return $query->first();
    }
}
