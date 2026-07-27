<?php

namespace Database\Factories\Domain\Reports\Models;

use App\Domain\Organisations\Models\Organisation;
use App\Domain\Reports\Models\SavedReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SavedReport> */
class SavedReportFactory extends Factory
{
    protected $model = SavedReport::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'organisation_id' => Organisation::factory(),
            'owner_user_id' => User::factory(),
            'name' => fake()->unique()->words(3, true),
            'report_key' => 'trial-balance',
            'filters' => [],
            'visibility' => 'private',
        ];
    }

    public function roleVisible(): static
    {
        return $this->state(['visibility' => 'role']);
    }

    public function organisationVisible(): static
    {
        return $this->state(['visibility' => 'organisation']);
    }

    public function favourite(): static
    {
        return $this->state(['is_favourite' => true]);
    }

    public function default(): static
    {
        return $this->state(['is_default' => true]);
    }
}
