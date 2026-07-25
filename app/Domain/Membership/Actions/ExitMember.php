<?php

namespace App\Domain\Membership\Actions;

use App\Domain\Membership\Enums\MemberStatus;
use App\Domain\Membership\Models\Member;
use App\Models\User;
use Carbon\CarbonInterface;

class ExitMember
{
    public function __construct(private TransitionMemberStatus $transition) {}

    public function handle(Member $member, User $actor, CarbonInterface $exitDate, ?string $reason): Member
    {
        return $this->transition->handle($member, MemberStatus::Exited, $actor, $reason, $exitDate);
    }
}
