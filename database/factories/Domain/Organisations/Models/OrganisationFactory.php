<?php

namespace Database\Factories\Domain\Organisations\Models;

use App\Domain\Organisations\Enums\OrganisationStatus;
use App\Domain\Organisations\Enums\OrganisationType;
use App\Domain\Organisations\Models\Organisation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Organisation>
 */
class OrganisationFactory extends Factory
{
    protected $model = Organisation::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'public_id' => (string) Str::uuid7(),
            'name' => fake()->company(),
            'code' => fake()->unique()->bothify('ORG-####'),
            'organisation_type' => fake()->randomElement(OrganisationType::cases()),
            'phone' => fake()->optional()->phoneNumber(),
            'email' => fake()->optional()->companyEmail(),
            'address' => fake()->optional()->address(),
            'logo_path' => null,
            'currency_code' => 'GHS',
            'timezone' => 'Africa/Accra',
            'status' => OrganisationStatus::Active,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (): array => ['status' => OrganisationStatus::Pending]);
    }

    public function suspended(): static
    {
        return $this->state(fn (): array => ['status' => OrganisationStatus::Suspended]);
    }
}
