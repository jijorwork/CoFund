<?php

namespace App\Domain\Contributions\Services;

use App\Domain\Contributions\Enums\ContributionPlanStatus;
use App\Domain\Contributions\Exceptions\InvalidContributionOperation;
use App\Domain\Contributions\Models\ContributionPlan;
use App\Domain\Contributions\Models\MemberPlanEnrolment;
use App\Domain\Membership\Enums\GroupMembershipStatus;
use App\Domain\Membership\Enums\MemberStatus;
use App\Domain\Membership\Models\GroupMembership;
use App\Domain\Membership\Models\Member;
use Carbon\CarbonImmutable;

class CheckContributionPlanEligibility
{
    public function __construct(
        private ContributionScope $scope,
        private ContributionPlanAmount $amounts,
    ) {}

    public function assertEligible(
        Member $member,
        ContributionPlan $plan,
        ?int $selectedAmountMinor,
        CarbonImmutable $startDate,
        ?CarbonImmutable $endDate,
    ): void {
        $this->scope->applyEnrolmentAccess(Member::query()->whereKey($member))->first()
            ?? throw new InvalidContributionOperation;

        if (
            $member->organisation_id !== $plan->organisation_id
            || $member->status !== MemberStatus::Active
            || $plan->status !== ContributionPlanStatus::Active
            || ($plan->start_date !== null && $startDate->isBefore($plan->start_date))
            || ($plan->end_date !== null && $startDate->isAfter($plan->end_date))
            || ($endDate !== null && $endDate->isBefore($startDate))
            || ($plan->branch_id !== null && $member->branch_id !== $plan->branch_id)
        ) {
            throw new InvalidContributionOperation;
        }

        if (
            $plan->group_id !== null
            && ! GroupMembership::query()
                ->where('group_id', $plan->group_id)
                ->whereBelongsTo($member)
                ->where('status', GroupMembershipStatus::Active)
                ->exists()
        ) {
            throw new InvalidContributionOperation;
        }

        if (MemberPlanEnrolment::query()->where(
            'active_enrolment_key',
            MemberPlanEnrolment::activeEnrolmentKey($plan, $member),
        )->exists()) {
            throw new InvalidContributionOperation;
        }

        $this->amounts->expectedAmount($plan, $selectedAmountMinor);
    }
}
