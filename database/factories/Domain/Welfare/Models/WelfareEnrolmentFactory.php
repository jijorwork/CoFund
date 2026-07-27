<?php

namespace Database\Factories\Domain\Welfare\Models;

use App\Domain\Membership\Models\Member;
use App\Domain\Welfare\Enums\WelfareEnrolmentStatus;
use App\Domain\Welfare\Models\WelfareEnrolment;
use App\Domain\Welfare\Models\WelfareProduct;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WelfareEnrolment> */
class WelfareEnrolmentFactory extends Factory
{
    protected $model = WelfareEnrolment::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['organisation_id' => fn (array $attributes) => Member::query()->whereKey($attributes['member_id'])->value('organisation_id'), 'member_id' => Member::factory(), 'welfare_product_id' => WelfareProduct::factory(), 'enrolment_number' => fake()->unique()->bothify('WEN-####'), 'enrolled_at' => today(), 'coverage_start_date' => today(), 'status' => WelfareEnrolmentStatus::Active, 'active_enrolment_key' => fake()->uuid(), 'created_by_user_id' => User::factory(), 'activated_at' => now()];
    }
}
