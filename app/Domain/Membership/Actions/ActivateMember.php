<?php

namespace App\Domain\Membership\Actions;

use App\Domain\Membership\Enums\MemberStatus;
use App\Domain\Membership\Models\Member;
use App\Models\User;

class ActivateMember
{
    public function __construct(private TransitionMemberStatus $transition) {}

    public function handle(Member $member, User $actor): Member
    {
        return $this->transition->handle($member, MemberStatus::Active, $actor);
    }
}
