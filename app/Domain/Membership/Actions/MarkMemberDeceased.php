<?php

namespace App\Domain\Membership\Actions;

use App\Domain\Membership\Enums\MemberStatus;
use App\Domain\Membership\Models\Member;
use App\Models\User;
use Carbon\CarbonInterface;

class MarkMemberDeceased
{
    public function __construct(private TransitionMemberStatus $transition) {}

    public function handle(Member $member, User $actor, CarbonInterface $date, ?string $reason): Member
    {
        return $this->transition->handle($member, MemberStatus::Deceased, $actor, $reason, $date);
    }
}
