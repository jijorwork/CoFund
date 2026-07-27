<?php

namespace App\Domain\Accounting\Queries;

use App\Domain\Accounting\Models\LedgerAccount;
use App\Domain\Accounting\Services\AccountingScope;
use App\Domain\Organisations\Tenancy\TenantContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ListLedgerAccounts
{
    public function __construct(private TenantContext $context, private AccountingScope $scope) {}

    /** @param array<string, mixed> $filters
     * @return LengthAwarePaginator<int, LedgerAccount>
     */
    public function handle(array $filters, string $sort): LengthAwarePaginator
    {
        $query = LedgerAccount::query()
            ->whereBelongsTo($this->context->organisation())
            ->with(['branch', 'parent'])
            ->when($filters['category'] ?? null, fn (Builder $query, string $value) => $query->where('category', $value))
            ->when($filters['purpose'] ?? null, fn (Builder $query, string $value) => $query->where('purpose', $value))
            ->when($filters['status'] ?? null, fn (Builder $query, string $value) => $query->where('status', $value))
            ->when($filters['currency_code'] ?? null, fn (Builder $query, string $value) => $query->where('currency_code', strtoupper($value)))
            ->when($filters['branch_uuid'] ?? null, fn (Builder $query, string $value) => $query->whereHas('branch', fn (Builder $query) => $query->where('public_id', $value)));
        $this->scope->apply($query, includeOrganisationWide: true);
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';

        return $query->orderBy(ltrim($sort, '-'), $direction)->paginate(25)->withQueryString();
    }
}
