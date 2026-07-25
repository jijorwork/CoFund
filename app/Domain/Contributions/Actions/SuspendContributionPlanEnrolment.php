<?php

namespace App\Domain\Contributions\Actions;

use App\Domain\Contributions\Enums\EnrolmentStatus;
use App\Domain\Contributions\Exceptions\InvalidContributionOperation;
use App\Domain\Contributions\Models\MemberPlanEnrolment;
use App\Models\User;

class SuspendContributionPlanEnrolment
{
    public function handle(MemberPlanEnrolment $enrolment, User $actor, string $reason): MemberPlanEnrolment
    {
        if ($enrolment->status !== EnrolmentStatus::Active || trim($reason) === '') {
            throw new InvalidContributionOperation;
        }

        $enrolment->update([
            'status' => EnrolmentStatus::Suspended,
            'suspended_at' => now(),
            'suspended_by_user_id' => $actor->getKey(),
            'suspension_reason' => $reason,
        ]);

        return $enrolment->refresh()->load(['contributionPlan', 'member', 'branch', 'group']);
    }
}
