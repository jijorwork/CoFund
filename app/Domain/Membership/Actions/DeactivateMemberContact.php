<?php

namespace App\Domain\Membership\Actions;

use App\Domain\Membership\Enums\MemberContactStatus;
use App\Domain\Membership\Exceptions\InvalidMembershipOperation;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Models\MemberContact;
use App\Domain\Membership\Services\MembershipBranchScope;

class DeactivateMemberContact
{
    public function __construct(private MembershipBranchScope $branchScope) {}

    public function handle(Member $member, MemberContact $contact): MemberContact
    {
        $this->branchScope->assertAccessibleRecord($member->branch_id);

        if ($contact->member_id !== $member->getKey()) {
            throw new InvalidMembershipOperation;
        }

        $contact->update([
            'status' => MemberContactStatus::Inactive,
            'primary_scope_key' => null,
        ]);

        return $contact->refresh();
    }
}
