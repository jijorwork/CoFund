<?php

namespace Database\Factories\Domain\Complaints\Models;

use App\Domain\Complaints\Models\ComplaintCase;
use App\Domain\Organisations\Models\Organisation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ComplaintCase>
 */
class ComplaintCaseFactory extends Factory
{
    protected $model = ComplaintCase::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'organisation_id' => Organisation::factory(),
            'branch_id' => null,
            'member_id' => null,
            'reported_by_user_id' => User::factory(),
            'assigned_to_user_id' => null,
            'subject' => fake()->sentence(),
            'category' => 'service',
        ];
    }
}
