<?php

namespace App\Domain\Membership\Actions;

use App\Domain\Membership\Exceptions\InvalidMembershipOperation;
use App\Domain\Membership\Models\Group;
use App\Domain\Membership\Services\MembershipBranchScope;
use App\Domain\Organisations\Models\Branch;
use App\Domain\Organisations\Tenancy\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UpdateGroup
{
    public function __construct(
        private TenantContext $tenantContext,
        private MembershipBranchScope $branchScope,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function handle(Group $group, array $attributes, ?Branch $branch): Group
    {
        $this->assertWithinTenant($group);
        $this->branchScope->assertAccessibleRecord($group->branch_id);
        $this->branchScope->assertAccessibleBranch($branch);
        $attributes = Arr::only($attributes, [
            'name',
            'code',
            'group_type',
            'description',
            'status',
        ]);

        try {
            return DB::transaction(function () use ($group, $attributes, $branch): Group {
                $lockedGroup = Group::query()
                    ->whereKey($group->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->assertWithinTenant($lockedGroup);
                $this->branchScope->assertAccessibleRecord($lockedGroup->branch_id);
                $lockedGroup->update([
                    ...$attributes,
                    'code' => array_key_exists('code', $attributes)
                        ? Str::upper((string) $attributes['code'])
                        : $lockedGroup->code,
                    'branch_id' => $branch?->getKey(),
                ]);

                return $lockedGroup->refresh()->load('branch');
            });
        } catch (QueryException $exception) {
            throw new InvalidMembershipOperation(
                'A group with that code already exists in the active organisation.',
                previous: $exception,
            );
        }
    }

    private function assertWithinTenant(Group $group): void
    {
        if ($group->organisation_id !== $this->tenantContext->organisation()->getKey()) {
            throw new InvalidMembershipOperation;
        }
    }
}
