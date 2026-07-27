<?php

namespace Database\Factories\Domain\Welfare\Models;

use App\Domain\Organisations\Models\Organisation;
use App\Domain\Welfare\Models\WelfareEventType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WelfareEventType> */
class WelfareEventTypeFactory extends Factory
{
    protected $model = WelfareEventType::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['organisation_id' => Organisation::factory(), 'code' => fake()->unique()->bothify('EVT-####'), 'name' => fake()->words(2, true), 'event_category' => 'bereavement', 'status' => 'active', 'created_by_user_id' => User::factory()];
    }
}
