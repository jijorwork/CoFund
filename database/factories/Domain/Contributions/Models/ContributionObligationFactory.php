<?php

namespace Database\Factories\Domain\Contributions\Models;

use App\Domain\Contributions\Enums\ObligationSatisfactionStatus;
use App\Domain\Contributions\Enums\ObligationStatus;
use App\Domain\Contributions\Models\ContributionObligation;
use App\Domain\Contributions\Models\MemberPlanEnrolment;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ContributionObligation> */
class ContributionObligationFactory extends Factory
{
    protected $model = ContributionObligation::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'member_plan_enrolment_id' => MemberPlanEnrolment::factory(),
            'organisation_id' => fn (array $attributes): int => MemberPlanEnrolment::query()->findOrFail((int) $attributes['member_plan_enrolment_id'])->organisation_id,
            'contribution_plan_id' => fn (array $attributes): int => MemberPlanEnrolment::query()->findOrFail((int) $attributes['member_plan_enrolment_id'])->contribution_plan_id,
            'member_id' => fn (array $attributes): int => MemberPlanEnrolment::query()->findOrFail((int) $attributes['member_plan_enrolment_id'])->member_id,
            'sequence_number' => fake()->unique()->numberBetween(1, 100000),
            'period_start' => now()->toDateString(),
            'period_end' => now()->toDateString(),
            'due_date' => now()->toDateString(),
            'expected_amount_minor' => 10000,
            'currency_code' => 'GHS',
            'schedule_status' => ObligationStatus::Due,
            'satisfaction_status' => ObligationSatisfactionStatus::Unsatisfied,
            'grace_due_date' => now()->addDays(3)->toDateString(),
            'satisfied_amount_minor' => 0,
            'outstanding_amount_minor' => 10000,
            'generated_at' => now(),
        ];
    }

    public function upcoming(): static
    {
        return $this->state(fn (): array => ['schedule_status' => ObligationStatus::Upcoming, 'due_date' => now()->addWeek()]);
    }

    public function due(): static
    {
        return $this->state(fn (): array => ['schedule_status' => ObligationStatus::Due, 'due_date' => now()]);
    }

    public function overdue(): static
    {
        return $this->state(fn (): array => ['schedule_status' => ObligationStatus::Overdue, 'due_date' => now()->subWeek(), 'grace_due_date' => now()->subDay()]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (): array => ['schedule_status' => ObligationStatus::Cancelled]);
    }

    public function daily(): static
    {
        return $this;
    }

    public function weekly(): static
    {
        return $this;
    }

    public function monthly(): static
    {
        return $this;
    }
}
