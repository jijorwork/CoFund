<?php

namespace Database\Factories\Domain\Welfare\Models;

use App\Domain\Membership\Models\Member;
use App\Domain\Welfare\Enums\WelfareEventStatus;
use App\Domain\Welfare\Models\WelfareEvent;
use App\Domain\Welfare\Models\WelfareEventType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WelfareEvent> */
class WelfareEventFactory extends Factory
{
    protected $model = WelfareEvent::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['organisation_id' => fn (array $attributes) => Member::query()->whereKey($attributes['member_id'])->value('organisation_id'), 'member_id' => Member::factory(), 'welfare_event_type_id' => WelfareEventType::factory(), 'event_number' => fake()->unique()->bothify('WEV-####'), 'event_date' => today(), 'reported_date' => today(), 'title' => fake()->sentence(4), 'status' => WelfareEventStatus::Reported, 'created_by_user_id' => User::factory()];
    }
}
