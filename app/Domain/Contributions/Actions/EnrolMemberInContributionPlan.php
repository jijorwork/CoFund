<?php

namespace App\Domain\Contributions\Actions;

use App\Domain\Contributions\Enums\ApprovalStatus;
use App\Domain\Contributions\Enums\EnrolmentStatus;
use App\Domain\Contributions\Models\ContributionPlan;
use App\Domain\Contributions\Models\MemberPlanEnrolment;
use App\Domain\Contributions\Services\CheckContributionPlanEligibility;
use App\Domain\Membership\Models\Member;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class EnrolMemberInContributionPlan
{
    public function __construct(
        private CheckContributionPlanEligibility $eligibility,
        private GenerateInitialContributionSchedule $schedule,
    ) {}

    public function handle(
        User $actor,
        Member $member,
        ContributionPlan $plan,
        ?int $selectedAmountMinor,
        CarbonImmutable $startDate,
        ?CarbonImmutable $endDate,
    ): MemberPlanEnrolment {
        $this->eligibility->assertEligible($member, $plan, $selectedAmountMinor, $startDate, $endDate);

        return DB::transaction(function () use ($actor, $member, $plan, $selectedAmountMinor, $startDate, $endDate): MemberPlanEnrolment {
            $requiresApproval = $plan->requires_approval_to_join;
            $enrolment = MemberPlanEnrolment::query()->create([
                'organisation_id' => $plan->organisation_id,
                'contribution_plan_id' => $plan->getKey(),
                'member_id' => $member->getKey(),
                'branch_id' => $member->branch_id,
                'group_id' => $plan->group_id,
                'selected_amount_minor' => $selectedAmountMinor,
                'currency_code' => $plan->currency_code,
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate?->toDateString(),
                'status' => $requiresApproval ? EnrolmentStatus::Pending : EnrolmentStatus::Active,
                'approval_status' => $requiresApproval ? ApprovalStatus::Pending : ApprovalStatus::NotRequired,
                'created_by_user_id' => $actor->getKey(),
                'active_enrolment_key' => MemberPlanEnrolment::activeEnrolmentKey($plan, $member),
            ])->load(['contributionPlan.organisation', 'member', 'branch', 'group']);

            return $requiresApproval ? $enrolment : $this->schedule->handle($enrolment);
        });
    }
}
