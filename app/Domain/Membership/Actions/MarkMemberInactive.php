<?php

namespace App\Domain\Membership\Actions;

use App\Domain\Membership\Enums\MemberStatus;
use App\Domain\Membership\Models\Member;
use App\Models\User;

class MarkMemberInactive
{
    public function __construct(private TransitionMemberStatus $transition) {}

    public function handle(Member $member, User $actor, ?string $reason = null): Member
    {
        return $this->transition->handle($member, MemberStatus::Inactive, $actor, $reason);
    }
}
