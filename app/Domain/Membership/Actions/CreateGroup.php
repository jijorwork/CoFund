<?php

namespace App\Domain\Membership\Actions;

use App\Domain\Identity\Auditing\ActingContext;
use App\Domain\Membership\Exceptions\InvalidMembershipOperation;
use App\Domain\Membership\Models\Group;
use App\Domain\Membership\Services\MembershipBranchScope;
use App\Domain\Organisations\Models\Branch;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class CreateGroup
{
    public function __construct(
        private TenantContext $tenantContext,
        private MembershipBranchScope $branchScope,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function handle(User $actor, array $attributes, ?Branch $branch): Group
    {
        $this->branchScope->assertAccessibleBranch($branch);
        $acting = ActingContext::from($actor, $this->tenantContext);
        $attributes = Arr::only($attributes, [
            'name',
            'code',
            'group_type',
            'description',
            'status',
        ]);

        try {
            return Group::query()->create([
                ...$attributes,
                'code' => Str::upper((string) ($attributes['code'] ?? '')),
                'organisation_id' => $acting->organisationId,
                'branch_id' => $branch?->getKey(),
                'created_by_user_id' => $acting->userId,
            ])->load('branch');
        } catch (QueryException $exception) {
            throw new InvalidMembershipOperation(
                'A group with that code already exists in the active organisation.',
                previous: $exception,
            );
        }
    }
}
