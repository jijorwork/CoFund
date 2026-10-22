<?php

namespace App\Domain\Membership\Actions;

use App\Domain\Membership\Exceptions\InvalidMembershipOperation;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Services\MembershipBranchScope;
use App\Domain\Organisations\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

class UnlinkMemberFromUser
{
    public function __construct(
        private MembershipBranchScope $branchScope,
        private TenantContext $tenantContext,
    ) {}

    public function handle(Member $member): Member
    {
        if ($member->organisation_id !== $this->tenantContext->organisation()->getKey()) {
            throw new InvalidMembershipOperation;
        }

        $this->branchScope->assertAccessibleRecord($member->branch_id);

        return DB::transaction(function () use ($member): Member {
            $locked = Member::query()->lockForUpdate()->whereKey($member->getKey())->firstOrFail();

            if ($locked->organisation_id !== $this->tenantContext->organisation()->getKey()) {
                throw new InvalidMembershipOperation;
            }

            $locked->update(['user_id' => null, 'active_user_link_key' => null]);

            return $locked->refresh()->load(['branch', 'user']);
        });
    }
}
