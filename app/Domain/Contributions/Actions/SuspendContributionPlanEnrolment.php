<?php

namespace App\Domain\Contributions\Actions;

use App\Domain\Contributions\Enums\EnrolmentStatus;
use App\Domain\Contributions\Exceptions\InvalidContributionOperation;
use App\Domain\Contributions\Models\MemberPlanEnrolment;
use App\Domain\Contributions\Services\ContributionScope;
use App\Models\User;

class SuspendContributionPlanEnrolment
{
    public function __construct(private ContributionScope $scope) {}

    public function handle(MemberPlanEnrolment $enrolment, User $actor, string $reason): MemberPlanEnrolment
    {
        $this->scope->assertAccessibleEnrolment($enrolment);

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
