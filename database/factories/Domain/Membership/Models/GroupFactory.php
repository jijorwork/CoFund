<?php

namespace Database\Factories\Domain\Membership\Models;

use App\Domain\Membership\Enums\GroupStatus;
use App\Domain\Membership\Enums\GroupType;
use App\Domain\Membership\Models\Group;
use App\Domain\Organisations\Models\Branch;
use App\Domain\Organisations\Models\Organisation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Group> */
class GroupFactory extends Factory
{
    protected $model = Group::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'organisation_id' => Organisation::factory(),
            'branch_id' => null,
            'name' => fake()->words(2, true),
            'code' => fake()->unique()->bothify('GRP-####'),
            'group_type' => GroupType::Other,
            'description' => fake()->optional()->sentence(),
            'status' => GroupStatus::Active,
            'created_by_user_id' => User::factory(),
        ];
    }

    public function active(): static
    {
        return $this->state(fn (): array => ['status' => GroupStatus::Active]);
    }

    public function suspended(): static
    {
        return $this->state(fn (): array => ['status' => GroupStatus::Suspended]);
    }

    public function organisationWide(): static
    {
        return $this->state(fn (): array => ['branch_id' => null]);
    }

    public function branchSpecific(): static
    {
        return $this->state(fn (array $attributes): array => [
            'branch_id' => Branch::factory()->state([
                'organisation_id' => $attributes['organisation_id'],
            ]),
        ]);
    }
}
