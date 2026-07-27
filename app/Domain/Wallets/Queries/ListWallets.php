<?php

namespace App\Domain\Wallets\Queries;

use App\Domain\Accounting\Services\AccountingScope;
use App\Domain\Membership\Models\Member;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Domain\Wallets\Models\Wallet;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ListWallets
{
    public function __construct(private TenantContext $context, private AccountingScope $scope) {}

    /** @param array<string, mixed> $filters
     * @return LengthAwarePaginator<int, Wallet>
     */
    public function handle(array $filters, string $sort): LengthAwarePaginator
    {
        $query = Wallet::query()
            ->whereBelongsTo($this->context->organisation())
            ->with(['branch', 'ledgerAccount', 'walletable'])
            ->when($filters['wallet_type'] ?? null, fn (Builder $query, string $value) => $query->where('wallet_type', $value))
            ->when($filters['status'] ?? null, fn (Builder $query, string $value) => $query->where('status', $value))
            ->when($filters['owner_type'] ?? null, fn (Builder $query, string $value) => $query->where('walletable_type', $value))
            ->when($filters['currency_code'] ?? null, fn (Builder $query, string $value) => $query->where('currency_code', strtoupper($value)))
            ->when($filters['branch_uuid'] ?? null, fn (Builder $query, string $value) => $query->whereHas('branch', fn (Builder $query) => $query->where('public_id', $value)))
            ->when($filters['member_uuid'] ?? null, fn (Builder $query, string $value) => $query
                ->where('walletable_type', 'member')
                ->whereHasMorph('walletable', [Member::class], fn (Builder $query) => $query->where('public_id', $value)));
        $this->scope->apply($query);

        if ($this->context->role()->name === 'collector') {
            $query->where('walletable_type', 'collector_user')
                ->where('walletable_id', $this->context->roleAssignment()->user_id);
        }

        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';

        return $query->orderBy(ltrim($sort, '-'), $direction)->paginate(25)->withQueryString();
    }
}
