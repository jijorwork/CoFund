<?php

namespace App\Domain\Contributions\Actions;

use App\Domain\Contributions\Enums\EnrolmentStatus;
use App\Domain\Contributions\Exceptions\InvalidContributionOperation;
use App\Domain\Contributions\Models\MemberPlanEnrolment;
use App\Domain\Contributions\Services\ContributionScope;
use App\Models\User;

class CancelContributionPlanEnrolment
{
    public function __construct(private ContributionScope $scope) {}

    public function handle(MemberPlanEnrolment $enrolment, User $actor, ?string $reason): MemberPlanEnrolment
    {
        $this->scope->assertAccessibleEnrolment($enrolment);

        if ($enrolment->status !== EnrolmentStatus::Pending) {
            throw new InvalidContributionOperation;
        }

        $enrolment->update([
            'status' => EnrolmentStatus::Cancelled,
            'ended_at' => now(),
            'ended_by_user_id' => $actor->getKey(),
            'end_reason' => $reason,
            'active_enrolment_key' => null,
        ]);

        return $enrolment->refresh()->load(['contributionPlan', 'member', 'branch', 'group']);
    }
}
