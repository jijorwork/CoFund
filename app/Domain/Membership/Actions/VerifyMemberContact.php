<?php

namespace App\Domain\Membership\Actions;

use App\Domain\Membership\Exceptions\InvalidMembershipOperation;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Models\MemberContact;
use App\Domain\Membership\Services\MembershipBranchScope;
use App\Models\User;

class VerifyMemberContact
{
    public function __construct(private MembershipBranchScope $branchScope) {}

    public function handle(Member $member, MemberContact $contact, User $actor): MemberContact
    {
        $this->branchScope->assertAccessibleRecord($member->branch_id);

        if ($contact->member_id !== $member->getKey()) {
            throw new InvalidMembershipOperation;
        }

        $contact->update(['verified_at' => now(), 'verified_by_user_id' => $actor->getKey()]);

        return $contact->refresh();
    }
}
