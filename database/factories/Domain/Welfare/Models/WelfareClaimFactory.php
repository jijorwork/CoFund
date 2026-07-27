<?php

namespace Database\Factories\Domain\Welfare\Models;

use App\Domain\Membership\Models\Member;
use App\Domain\Welfare\Models\WelfareClaim;
use App\Domain\Welfare\Models\WelfareEnrolment;
use App\Domain\Welfare\Models\WelfareEvent;
use App\Domain\Welfare\Models\WelfareEventType;
use App\Domain\Welfare\Models\WelfareProduct;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WelfareClaim> */
class WelfareClaimFactory extends Factory
{
    protected $model = WelfareClaim::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['organisation_id' => fn (array $attributes) => Member::query()->whereKey($attributes['member_id'])->value('organisation_id'), 'claim_number' => fake()->unique()->bothify('WCL-####'), 'member_id' => Member::factory(), 'welfare_product_id' => WelfareProduct::factory(), 'welfare_enrolment_id' => WelfareEnrolment::factory(), 'welfare_event_id' => WelfareEvent::factory(), 'welfare_event_type_id' => WelfareEventType::factory(), 'claimant_type' => 'member', 'requested_benefit_minor' => 10000, 'currency_code' => 'GHS', 'claim_date' => today(), 'created_by_user_id' => User::factory()];
    }
}
