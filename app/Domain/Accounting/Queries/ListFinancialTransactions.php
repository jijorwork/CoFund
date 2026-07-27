<?php

namespace App\Domain\Accounting\Queries;

use App\Domain\Accounting\Models\FinancialTransaction;
use App\Domain\Accounting\Services\AccountingScope;
use App\Domain\Organisations\Tenancy\TenantContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ListFinancialTransactions
{
    public function __construct(private TenantContext $context, private AccountingScope $scope) {}

    /** @param array<string, mixed> $filters
     * @return LengthAwarePaginator<int, FinancialTransaction>
     */
    public function handle(array $filters, string $sort): LengthAwarePaginator
    {
        $query = FinancialTransaction::query()
            ->whereBelongsTo($this->context->organisation())
            ->with(['branch', 'originalTransaction'])
            ->when($filters['transaction_number'] ?? null, fn (Builder $query, string $value) => $query->where('transaction_number', $value))
            ->when($filters['transaction_type'] ?? null, fn (Builder $query, string $value) => $query->where('transaction_type', $value))
            ->when($filters['status'] ?? null, fn (Builder $query, string $value) => $query->where('status', $value))
            ->when($filters['external_reference'] ?? null, fn (Builder $query, string $value) => $query->where('external_reference', $value))
            ->when($filters['transaction_date_from'] ?? null, fn (Builder $query, string $value) => $query->whereDate('transaction_date', '>=', $value))
            ->when($filters['transaction_date_to'] ?? null, fn (Builder $query, string $value) => $query->whereDate('transaction_date', '<=', $value))
            ->when($filters['branch_uuid'] ?? null, fn (Builder $query, string $value) => $query->whereHas('branch', fn (Builder $query) => $query->where('public_id', $value)))
            ->when($filters['member_uuid'] ?? null, fn (Builder $query, string $value) => $query->whereHas('entries.member', fn (Builder $query) => $query->where('public_id', $value)))
            ->when($filters['wallet_uuid'] ?? null, fn (Builder $query, string $value) => $query->whereHas('entries.wallet', fn (Builder $query) => $query->where('public_id', $value)))
            ->when($filters['account_uuid'] ?? null, fn (Builder $query, string $value) => $query->whereHas('entries.ledgerAccount', fn (Builder $query) => $query->where('public_id', $value)));
        $this->scope->apply($query);
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';

        return $query->orderBy(ltrim($sort, '-'), $direction)->paginate(25)->withQueryString();
    }
}
