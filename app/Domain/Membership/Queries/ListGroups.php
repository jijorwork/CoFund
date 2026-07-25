<?php

namespace App\Domain\Membership\Queries;

use App\Domain\Membership\Models\Group;
use App\Domain\Membership\Models\GroupMembership;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Services\MembershipBranchScope;
use App\Domain\Organisations\Tenancy\TenantContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ListGroups
{
    public function __construct(
        private TenantContext $tenantContext,
        private MembershipBranchScope $branchScope,
    ) {}

    /** @param array<string, mixed> $filters
     * @return LengthAwarePaginator<int, Group>
     */
    public function handle(array $filters): LengthAwarePaginator
    {
        $query = Group::query()
            ->whereBelongsTo($this->tenantContext->organisation())
            ->with('branch')
            ->withCount(['memberships as active_members_count' => fn (Builder $query) => $query->where('status', 'active')])
            ->when($filters['status'] ?? null, fn (Builder $query, string $value) => $query->where('status', $value))
            ->when($filters['group_type'] ?? null, fn (Builder $query, string $value) => $query->where('group_type', $value));
        $this->branchScope->apply($query);

        return $query->latest()->paginate(25)->withQueryString();
    }

    /** @return LengthAwarePaginator<int, Member> */
    public function members(Group $group): LengthAwarePaginator
    {
        return Member::query()
            ->whereIn('id', GroupMembership::query()
                ->select('member_id')
                ->whereBelongsTo($group)
                ->where('status', 'active'))
            ->with(['branch', 'user'])
            ->orderBy('membership_number')
            ->paginate(25);
    }
}
