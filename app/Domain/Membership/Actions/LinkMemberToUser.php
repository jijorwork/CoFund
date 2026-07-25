<?php

namespace App\Domain\Membership\Actions;

use App\Domain\Membership\Enums\MemberStatus;
use App\Domain\Membership\Exceptions\InvalidMembershipOperation;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Services\MembershipBranchScope;
use App\Domain\Organisations\Services\OrganisationAccessService;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class LinkMemberToUser
{
    public function __construct(
        private OrganisationAccessService $organisationAccess,
        private MembershipBranchScope $branchScope,
    ) {}

    public function handle(Member $member, User $user): Member
    {
        $this->branchScope->assertAccessibleRecord($member->branch_id);

        if (! $this->organisationAccess->hasActiveAccess($user, $member->organisation)) {
            throw new InvalidMembershipOperation;
        }

        return DB::transaction(function () use ($member, $user): Member {
            $locked = Member::query()->lockForUpdate()->whereKey($member->getKey())->firstOrFail();

            if ($locked->status === MemberStatus::Exited || $locked->status === MemberStatus::Deceased) {
                throw new InvalidMembershipOperation;
            }

            $key = Member::activeUserLinkKey($locked->organisation, $user);

            if (Member::query()->where('active_user_link_key', $key)->whereKeyNot($locked)->exists()) {
                throw new InvalidMembershipOperation;
            }

            $locked->update(['user_id' => $user->getKey(), 'active_user_link_key' => $key]);

            return $locked->refresh()->load(['branch', 'user']);
        });
    }
}
