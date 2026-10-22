<?php

namespace Database\Factories\Domain\Complaints\Models;

use App\Domain\Complaints\Models\ComplaintCase;
use App\Domain\Complaints\Models\ComplaintMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ComplaintMessage>
 */
class ComplaintMessageFactory extends Factory
{
    protected $model = ComplaintMessage::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'complaint_case_id' => ComplaintCase::factory(),
            'organisation_id' => fn (array $attributes): int => ComplaintCase::query()->whereKey($attributes['complaint_case_id'])->sole()->organisation_id,
            'author_user_id' => User::factory(),
            'visibility' => 'internal',
            'body' => fake()->paragraph(),
        ];
    }

    public function forCase(ComplaintCase $case): static
    {
        return $this->state(fn (): array => [
            'complaint_case_id' => $case->getKey(),
            'organisation_id' => $case->organisation_id,
        ]);
    }
}
