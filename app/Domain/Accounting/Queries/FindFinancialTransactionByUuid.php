<?php

namespace App\Domain\Accounting\Queries;

use App\Domain\Accounting\Models\FinancialTransaction;
use App\Domain\Accounting\Services\AccountingScope;
use App\Domain\Organisations\Tenancy\TenantContext;

class FindFinancialTransactionByUuid
{
    public function __construct(private TenantContext $context, private AccountingScope $scope) {}

    public function handle(string $uuid): ?FinancialTransaction
    {
        $query = FinancialTransaction::query()
            ->whereBelongsTo($this->context->organisation())
            ->with([
                'branch', 'entries.ledgerAccount', 'entries.wallet',
                'entries.member', 'originalTransaction', 'reversalTransaction',
            ])
            ->where('public_id', $uuid);
        $this->scope->apply($query);

        return $query->first();
    }
}
