<?php

namespace Database\Factories\Domain\Membership\Models;

use App\Domain\Membership\Enums\MemberStatus;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Models\MemberStatusHistory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MemberStatusHistory> */
class MemberStatusHistoryFactory extends Factory
{
    protected $model = MemberStatusHistory::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'member_id' => Member::factory(),
            'organisation_id' => fn (array $attributes): int => Member::query()->findOrFail((int) $attributes['member_id'])->organisation_id,
            'from_status' => MemberStatus::Pending,
            'to_status' => MemberStatus::Active,
            'reason' => null,
            'effective_at' => now(),
            'changed_by_user_id' => User::factory(),
            'acting_role_id' => null,
            'metadata' => null,
        ];
    }
}
