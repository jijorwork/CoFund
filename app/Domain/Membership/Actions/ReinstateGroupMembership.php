<?php

namespace App\Domain\Membership\Actions;

use App\Domain\Membership\Enums\GroupMembershipStatus;
use App\Domain\Membership\Exceptions\InvalidMembershipOperation;
use App\Domain\Membership\Models\GroupMembership;
use App\Domain\Membership\Services\MembershipBranchScope;
use Illuminate\Support\Facades\DB;

class ReinstateGroupMembership
{
    public function __construct(private MembershipBranchScope $branchScope) {}

    public function handle(GroupMembership $membership): GroupMembership
    {
        $this->branchScope->assertAccessibleRecord($membership->group->branch_id);

        return DB::transaction(function () use ($membership): GroupMembership {
            $locked = GroupMembership::query()
                ->lockForUpdate()
                ->whereKey($membership->getKey())
                ->firstOrFail();
            $key = GroupMembership::activeMembershipKey($locked->group, $locked->member);

            if (
                $locked->status !== GroupMembershipStatus::Suspended
                || GroupMembership::query()->where('active_membership_key', $key)->exists()
            ) {
                throw new InvalidMembershipOperation;
            }

            $locked->update([
                'status' => GroupMembershipStatus::Active,
                'left_at' => null,
                'active_membership_key' => $key,
            ]);

            return $locked->refresh();
        });
    }
}
