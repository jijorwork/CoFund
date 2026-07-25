<?php

namespace Database\Factories\Domain\Membership\Models;

use App\Domain\Membership\Models\MembershipNumberSequence;
use App\Domain\Organisations\Models\Organisation;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MembershipNumberSequence> */
class MembershipNumberSequenceFactory extends Factory
{
    protected $model = MembershipNumberSequence::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'organisation_id' => Organisation::factory(),
            'sequence_year' => now()->year,
            'current_value' => fake()->numberBetween(1, 100),
        ];
    }
}
