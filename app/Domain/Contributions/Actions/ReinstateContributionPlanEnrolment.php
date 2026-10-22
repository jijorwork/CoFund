<?php

namespace App\Domain\Contributions\Actions;

use App\Domain\Contributions\Enums\EnrolmentStatus;
use App\Domain\Contributions\Exceptions\InvalidContributionOperation;
use App\Domain\Contributions\Models\MemberPlanEnrolment;
use App\Domain\Contributions\Services\ContributionScope;

class ReinstateContributionPlanEnrolment
{
    public function __construct(
        private ExtendContributionSchedule $schedule,
        private ContributionScope $scope,
    ) {}

    public function handle(MemberPlanEnrolment $enrolment): MemberPlanEnrolment
    {
        $this->scope->assertAccessibleEnrolment($enrolment);

        if ($enrolment->status !== EnrolmentStatus::Suspended) {
            throw new InvalidContributionOperation;
        }

        $enrolment->update([
            'status' => EnrolmentStatus::Active,
            'suspended_at' => null,
            'suspended_by_user_id' => null,
            'suspension_reason' => null,
        ]);

        return $this->schedule->handle($enrolment->refresh());
    }
}
