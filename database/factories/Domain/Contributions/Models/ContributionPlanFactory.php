<?php

namespace Database\Factories\Domain\Contributions\Models;

use App\Domain\Contributions\Enums\ContributionAmountType;
use App\Domain\Contributions\Enums\ContributionFrequency;
use App\Domain\Contributions\Enums\ContributionPlanStatus;
use App\Domain\Contributions\Enums\ContributionPlanType;
use App\Domain\Contributions\Models\ContributionPlan;
use App\Domain\Membership\Models\Group;
use App\Domain\Organisations\Models\Branch;
use App\Domain\Organisations\Models\Organisation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ContributionPlan> */
class ContributionPlanFactory extends Factory
{
    protected $model = ContributionPlan::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'organisation_id' => Organisation::factory(),
            'branch_id' => null,
            'group_id' => null,
            'name' => fake()->words(3, true),
            'code' => fake()->unique()->bothify('PLAN-####'),
            'plan_type' => ContributionPlanType::RegularSavings,
            'frequency' => ContributionFrequency::Monthly,
            'amount_type' => ContributionAmountType::Fixed,
            'fixed_amount_minor' => 10000,
            'currency_code' => 'GHS',
            'start_date' => now()->toDateString(),
            'collection_day_of_month' => min(now()->day, 28),
            'grace_period_days' => 3,
            'status' => ContributionPlanStatus::Active,
            'activated_at' => now(),
            'created_by_user_id' => User::factory(),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (): array => ['status' => ContributionPlanStatus::Draft, 'activated_at' => null]);
    }

    public function active(): static
    {
        return $this->state(fn (): array => ['status' => ContributionPlanStatus::Active, 'activated_at' => now()]);
    }

    public function suspended(): static
    {
        return $this->state(fn (): array => ['status' => ContributionPlanStatus::Suspended, 'suspended_at' => now()]);
    }

    public function closed(): static
    {
        return $this->state(fn (): array => ['status' => ContributionPlanStatus::Closed, 'closed_at' => now()]);
    }

    public function daily(): static
    {
        return $this->state(fn (): array => ['frequency' => ContributionFrequency::Daily, 'collection_day_of_month' => null]);
    }

    public function weekly(): static
    {
        return $this->state(fn (): array => ['frequency' => ContributionFrequency::Weekly, 'collection_day_of_week' => 1, 'collection_day_of_month' => null]);
    }

    public function monthly(): static
    {
        return $this->state(fn (): array => ['frequency' => ContributionFrequency::Monthly]);
    }

    public function flexible(): static
    {
        return $this->state(fn (): array => [
            'frequency' => ContributionFrequency::Flexible,
            'amount_type' => ContributionAmountType::Flexible,
            'fixed_amount_minor' => null,
            'collection_day_of_month' => null,
        ]);
    }

    public function oneTime(): static
    {
        return $this->state(fn (): array => ['frequency' => ContributionFrequency::OneTime, 'collection_day_of_month' => null]);
    }

    public function fixedAmount(int $amount = 10000): static
    {
        return $this->state(fn (): array => ['amount_type' => ContributionAmountType::Fixed, 'fixed_amount_minor' => $amount]);
    }

    public function memberSelectedAmount(int $minimum = 5000, int $maximum = 50000): static
    {
        return $this->state(fn (): array => [
            'amount_type' => ContributionAmountType::MemberSelected,
            'fixed_amount_minor' => null,
            'minimum_amount_minor' => $minimum,
            'maximum_amount_minor' => $maximum,
        ]);
    }

    public function branchSpecific(): static
    {
        return $this->state(fn (array $attributes): array => [
            'branch_id' => Branch::factory()->state(['organisation_id' => $attributes['organisation_id']]),
        ]);
    }

    public function groupSpecific(): static
    {
        return $this->state(fn (array $attributes): array => [
            'group_id' => Group::factory()->state([
                'organisation_id' => $attributes['organisation_id'],
                'branch_id' => $attributes['branch_id'] ?? null,
            ]),
        ]);
    }
}
