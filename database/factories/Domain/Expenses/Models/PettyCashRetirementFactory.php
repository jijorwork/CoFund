<?php

namespace Database\Factories\Domain\Expenses\Models;

use App\Domain\Expenses\Models\PettyCashAdvance;
use App\Domain\Expenses\Models\PettyCashRetirement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PettyCashRetirement>
 */
class PettyCashRetirementFactory extends Factory
{
    protected $model = PettyCashRetirement::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'petty_cash_advance_id' => PettyCashAdvance::factory(),
            'organisation_id' => fn (array $attributes) => PettyCashAdvance::query()->whereKey($attributes['petty_cash_advance_id'])->firstOrFail()->organisation_id,
            'retirement_number' => fake()->unique()->bothify('ORG-PCR-2026-######'),
            'submitted_amount_minor' => 10000, 'currency_code' => 'GHS',
            'status' => 'draft', 'submitted_by_user_id' => User::factory(),
        ];
    }
}
