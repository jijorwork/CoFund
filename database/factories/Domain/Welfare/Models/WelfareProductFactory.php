<?php

namespace Database\Factories\Domain\Welfare\Models;

use App\Domain\Organisations\Models\Organisation;
use App\Domain\Welfare\Enums\WelfareProductStatus;
use App\Domain\Welfare\Models\WelfareProduct;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WelfareProduct> */
class WelfareProductFactory extends Factory
{
    protected $model = WelfareProduct::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['organisation_id' => Organisation::factory(), 'code' => fake()->unique()->bothify('WEL-####'), 'name' => fake()->words(3, true), 'product_type' => 'general_welfare', 'currency_code' => 'GHS', 'benefit_calculation_type' => 'fixed', 'fixed_benefit_minor' => 10000, 'status' => WelfareProductStatus::Active, 'created_by_user_id' => User::factory(), 'activated_at' => now()];
    }
}
