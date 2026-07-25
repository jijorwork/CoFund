<?php

namespace Database\Factories\Domain\Organisations\Models;

use App\Domain\Organisations\Enums\SettingValueType;
use App\Domain\Organisations\Models\Organisation;
use App\Domain\Organisations\Models\OrganisationSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrganisationSetting>
 */
class OrganisationSettingFactory extends Factory
{
    protected $model = OrganisationSetting::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organisation_id' => Organisation::factory(),
            'key' => fake()->unique()->slug(2),
            'value' => fake()->word(),
            'value_type' => SettingValueType::String,
            'is_public' => false,
        ];
    }
}
