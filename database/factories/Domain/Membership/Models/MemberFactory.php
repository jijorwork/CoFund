<?php

namespace Database\Factories\Domain\Membership\Models;

use App\Domain\Membership\Enums\MemberStatus;
use App\Domain\Membership\Models\Member;
use App\Domain\Organisations\Models\Branch;
use App\Domain\Organisations\Models\Organisation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Member> */
class MemberFactory extends Factory
{
    protected $model = Member::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'organisation_id' => Organisation::factory(),
            'branch_id' => null,
            'user_id' => null,
            'membership_number' => fn (): string => 'MEM-'.fake()->unique()->numerify('######'),
            'first_name' => fake()->firstName(),
            'middle_name' => null,
            'last_name' => fake()->lastName(),
            'preferred_name' => null,
            'gender' => null,
            'date_of_birth' => fake()->optional()->dateTimeBetween('-80 years', '-18 years'),
            'phone' => '+233'.fake()->numerify('#########'),
            'alternate_phone' => null,
            'email' => fake()->optional()->safeEmail(),
            'joined_at' => now()->toDateString(),
            'status' => MemberStatus::Active,
            'created_by_user_id' => User::factory(),
            'active_user_link_key' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (): array => ['status' => MemberStatus::Pending]);
    }

    public function active(): static
    {
        return $this->state(fn (): array => ['status' => MemberStatus::Active]);
    }

    public function suspended(): static
    {
        return $this->state(fn (): array => [
            'status' => MemberStatus::Suspended,
            'suspended_at' => now(),
            'suspension_reason' => 'Factory suspension',
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['status' => MemberStatus::Inactive]);
    }

    public function exited(): static
    {
        return $this->state(fn (): array => [
            'status' => MemberStatus::Exited,
            'exited_at' => now()->toDateString(),
        ]);
    }

    public function deceased(): static
    {
        return $this->state(fn (): array => [
            'status' => MemberStatus::Deceased,
            'deceased_at' => now()->toDateString(),
        ]);
    }

    public function branchless(): static
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

    public function linkedToUser(): static
    {
        return $this->for(User::factory())->afterMaking(function (Member $member): void {
            $member->active_user_link_key = Member::activeUserLinkKey(
                $member->organisation,
                $member->user,
            );
        });
    }
}
