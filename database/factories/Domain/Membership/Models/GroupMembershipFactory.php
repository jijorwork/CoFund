<?php

namespace Database\Factories\Domain\Membership\Models;

use App\Domain\Membership\Enums\GroupMembershipStatus;
use App\Domain\Membership\Models\Group;
use App\Domain\Membership\Models\GroupMembership;
use App\Domain\Membership\Models\Member;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<GroupMembership> */
class GroupMembershipFactory extends Factory
{
    protected $model = GroupMembership::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'group_id' => Group::factory(),
            'member_id' => function (array $attributes): Member {
                $group = Group::query()->findOrFail((int) $attributes['group_id']);

                return Member::factory()->for($group->organisation)->create();
            },
            'organisation_id' => fn (array $attributes): int => Group::query()->findOrFail((int) $attributes['group_id'])->organisation_id,
            'joined_at' => now()->toDateString(),
            'left_at' => null,
            'status' => GroupMembershipStatus::Active,
            'assigned_by_user_id' => User::factory(),
            'active_membership_key' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (GroupMembership $membership): void {
            if ($membership->status === GroupMembershipStatus::Active) {
                $membership->active_membership_key = GroupMembership::activeMembershipKey(
                    $membership->group,
                    $membership->member,
                );
            }
        });
    }

    public function active(): static
    {
        return $this->state(fn (): array => ['status' => GroupMembershipStatus::Active]);
    }

    public function suspended(): static
    {
        return $this->state(fn (): array => [
            'status' => GroupMembershipStatus::Suspended,
            'active_membership_key' => null,
        ]);
    }

    public function left(): static
    {
        return $this->state(fn (): array => [
            'status' => GroupMembershipStatus::Left,
            'left_at' => now()->toDateString(),
            'active_membership_key' => null,
        ]);
    }
}
