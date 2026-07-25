<?php

namespace App\Domain\Membership\Queries;

use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Services\MembershipBranchScope;
use App\Domain\Membership\Services\PhoneNumberNormalizer;
use App\Domain\Organisations\Tenancy\TenantContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ListMembers
{
    public function __construct(
        private TenantContext $tenantContext,
        private MembershipBranchScope $branchScope,
        private PhoneNumberNormalizer $phones,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Member>
     */
    public function handle(array $filters, string $sort = '-created_at'): LengthAwarePaginator
    {
        $query = Member::query()
            ->whereBelongsTo($this->tenantContext->organisation())
            ->with(['branch', 'user']);

        $this->branchScope->apply($query);

        $query
            ->when($filters['membership_number'] ?? null, fn (Builder $query, string $value) => $query->where('membership_number', $value))
            ->when($filters['name'] ?? null, fn (Builder $query, string $value) => $query->where(function (Builder $query) use ($value): void {
                $query->where('first_name', 'like', "%{$value}%")
                    ->orWhere('middle_name', 'like', "%{$value}%")
                    ->orWhere('last_name', 'like', "%{$value}%");
            }))
            ->when($filters['phone'] ?? null, fn (Builder $query, string $value) => $query->where('phone', $this->phones->normalize($value)))
            ->when($filters['email'] ?? null, fn (Builder $query, string $value) => $query->where('email', $value))
            ->when($filters['branch_uuid'] ?? null, fn (Builder $query, string $value) => $query->whereHas('branch', fn (Builder $query) => $query->where('public_id', $value)))
            ->when($filters['status'] ?? null, fn (Builder $query, string $value) => $query->where('status', $value))
            ->when($filters['group_uuid'] ?? null, fn (Builder $query, string $value) => $query->whereHas('groupMemberships.group', fn (Builder $query) => $query->where('public_id', $value)))
            ->when($filters['joined_from'] ?? null, fn (Builder $query, string $value) => $query->whereDate('joined_at', '>=', $value))
            ->when($filters['joined_to'] ?? null, fn (Builder $query, string $value) => $query->whereDate('joined_at', '<=', $value));

        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');

        return $query->orderBy($column, $direction)->paginate(25)->withQueryString();
    }
}
