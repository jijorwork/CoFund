<?php

namespace Database\Factories\Domain\Organisations\Models;

use App\Domain\Organisations\Enums\BranchStatus;
use App\Domain\Organisations\Models\Branch;
use App\Domain\Organisations\Models\Organisation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Branch>
 */
class BranchFactory extends Factory
{
    protected $model = Branch::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'public_id' => (string) Str::uuid7(),
            'organisation_id' => Organisation::factory(),
            'name' => fake()->city().' Branch',
            'code' => fake()->unique()->bothify('BR-####'),
            'phone' => fake()->optional()->phoneNumber(),
            'email' => fake()->optional()->companyEmail(),
            'address' => fake()->optional()->address(),
            'status' => BranchStatus::Active,
        ];
    }

    public function suspended(): static
    {
        return $this->state(fn (): array => ['status' => BranchStatus::Suspended]);
    }
}
