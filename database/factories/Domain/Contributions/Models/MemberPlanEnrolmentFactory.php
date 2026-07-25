<?php

namespace Database\Factories\Domain\Contributions\Models;

use App\Domain\Contributions\Enums\ApprovalStatus;
use App\Domain\Contributions\Enums\EnrolmentStatus;
use App\Domain\Contributions\Models\ContributionPlan;
use App\Domain\Contributions\Models\MemberPlanEnrolment;
use App\Domain\Membership\Models\Member;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MemberPlanEnrolment> */
class MemberPlanEnrolmentFactory extends Factory
{
    protected $model = MemberPlanEnrolment::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'contribution_plan_id' => ContributionPlan::factory(),
            'member_id' => function (array $attributes): Member {
                $plan = ContributionPlan::query()->findOrFail((int) $attributes['contribution_plan_id']);

                return Member::factory()->for($plan->organisation)->create(['branch_id' => $plan->branch_id]);
            },
            'organisation_id' => fn (array $attributes): int => ContributionPlan::query()->findOrFail((int) $attributes['contribution_plan_id'])->organisation_id,
            'branch_id' => fn (array $attributes): ?int => Member::query()->findOrFail((int) $attributes['member_id'])->branch_id,
            'group_id' => fn (array $attributes): ?int => ContributionPlan::query()->findOrFail((int) $attributes['contribution_plan_id'])->group_id,
            'currency_code' => fn (array $attributes): string => ContributionPlan::query()->findOrFail((int) $attributes['contribution_plan_id'])->currency_code,
            'start_date' => now()->toDateString(),
            'status' => EnrolmentStatus::Active,
            'approval_status' => ApprovalStatus::NotRequired,
            'created_by_user_id' => User::factory(),
            'active_enrolment_key' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (MemberPlanEnrolment $enrolment): void {
            if (in_array($enrolment->status, [EnrolmentStatus::Pending, EnrolmentStatus::Active, EnrolmentStatus::Suspended], true)) {
                $enrolment->active_enrolment_key = MemberPlanEnrolment::activeEnrolmentKey(
                    $enrolment->contributionPlan,
                    $enrolment->member,
                );
            }
        });
    }

    public function pending(): static
    {
        return $this->state(fn (): array => ['status' => EnrolmentStatus::Pending, 'approval_status' => ApprovalStatus::Pending]);
    }

    public function active(): static
    {
        return $this->state(fn (): array => ['status' => EnrolmentStatus::Active]);
    }

    public function suspended(): static
    {
        return $this->state(fn (): array => ['status' => EnrolmentStatus::Suspended, 'suspended_at' => now()]);
    }

    public function completed(): static
    {
        return $this->state(fn (): array => ['status' => EnrolmentStatus::Completed, 'active_enrolment_key' => null]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (): array => ['status' => EnrolmentStatus::Cancelled, 'active_enrolment_key' => null]);
    }

    public function ended(): static
    {
        return $this->state(fn (): array => ['status' => EnrolmentStatus::Ended, 'ended_at' => now(), 'active_enrolment_key' => null]);
    }

    public function approvalRequired(): static
    {
        return $this->pending();
    }

    public function organisationWide(): static
    {
        return $this->state(fn (): array => ['branch_id' => null, 'group_id' => null]);
    }

    public function branchSpecific(): static
    {
        return $this->state(fn (array $attributes): array => ['branch_id' => Member::query()->findOrFail((int) $attributes['member_id'])->branch_id]);
    }

    public function groupSpecific(): static
    {
        return $this->state(fn (array $attributes): array => ['group_id' => ContributionPlan::query()->findOrFail((int) $attributes['contribution_plan_id'])->group_id]);
    }
}
