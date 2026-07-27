<?php

namespace Database\Factories\Domain\Welfare\Models;

use App\Domain\Identity\Models\Role;
use App\Domain\Welfare\Models\WelfareClaim;
use App\Domain\Welfare\Models\WelfareClaimApproval;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WelfareClaimApproval> */
class WelfareClaimApprovalFactory extends Factory
{
    protected $model = WelfareClaimApproval::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['organisation_id' => fn (array $attributes) => WelfareClaim::query()->whereKey($attributes['welfare_claim_id'])->value('organisation_id'), 'welfare_claim_id' => WelfareClaim::factory(), 'approval_level' => 'primary', 'decision' => 'approved', 'requested_at' => now(), 'decided_at' => now(), 'decided_by_user_id' => User::factory(), 'acting_role_id' => fn () => Role::query()->firstOrCreate(['name' => 'welfare-factory-role', 'guard_name' => 'web'])->getKey(), 'sequence_number' => 1];
    }
}
