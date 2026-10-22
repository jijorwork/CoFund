<?php

namespace App\Domain\Contributions\Actions;

use App\Domain\Contributions\Enums\ApprovalStatus;
use App\Domain\Contributions\Enums\EnrolmentStatus;
use App\Domain\Contributions\Exceptions\InvalidContributionOperation;
use App\Domain\Contributions\Models\MemberPlanEnrolment;
use App\Domain\Contributions\Services\ContributionScope;
use App\Models\User;

class RejectContributionPlanEnrolment
{
    public function __construct(private ContributionScope $scope) {}

    public function handle(MemberPlanEnrolment $enrolment, User $actor, ?string $reason): MemberPlanEnrolment
    {
        $this->scope->assertAccessibleEnrolment($enrolment);

        if (
            $enrolment->status !== EnrolmentStatus::Pending
            || $enrolment->approval_status !== ApprovalStatus::Pending
        ) {
            throw new InvalidContributionOperation;
        }

        $enrolment->update([
            'status' => EnrolmentStatus::Cancelled,
            'approval_status' => ApprovalStatus::Rejected,
            'approved_by_user_id' => $actor->getKey(),
            'approved_at' => now(),
            'ended_at' => now()->toDateString(),
            'ended_by_user_id' => $actor->getKey(),
            'end_reason' => $reason,
            'active_enrolment_key' => null,
        ]);

        return $enrolment->refresh()->load(['contributionPlan', 'member', 'branch', 'group']);
    }
}
