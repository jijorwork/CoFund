<?php

namespace Database\Factories\Domain\Withdrawals\Models;

use App\Domain\Identity\Models\Role;
use App\Domain\Withdrawals\Models\WithdrawalApproval;
use App\Domain\Withdrawals\Models\WithdrawalRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WithdrawalApproval> */
class WithdrawalApprovalFactory extends Factory
{
    protected $model = WithdrawalApproval::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['organisation_id' => fn (array $attributes) => WithdrawalRequest::query()->whereKey($attributes['withdrawal_request_id'])->value('organisation_id'), 'withdrawal_request_id' => WithdrawalRequest::factory(), 'approval_level' => 'primary', 'decision' => 'approved', 'requested_at' => now(), 'decided_at' => now(), 'decided_by_user_id' => User::factory(), 'acting_role_id' => fn () => Role::query()->firstOrCreate(['name' => 'withdrawal-factory-role', 'guard_name' => 'web'])->getKey(), 'sequence_number' => 1];
    }
}
