<?php

namespace Database\Factories\Domain\Reports\Models;

use App\Domain\Organisations\Models\Organisation;
use App\Domain\Reports\Models\ReportRun;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ReportRun> */
class ReportRunFactory extends Factory
{
    protected $model = ReportRun::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'organisation_id' => Organisation::factory(),
            'user_id' => User::factory(),
            'report_key' => 'trial-balance',
            'filters' => [],
            'status' => 'completed',
            'row_count' => 0,
            'duration_ms' => 10,
            'started_at' => now(),
            'completed_at' => now(),
        ];
    }

    public function failed(): static
    {
        return $this->state(['status' => 'failed', 'completed_at' => null, 'failed_at' => now(), 'failure_reason' => 'Safe failure reason.']);
    }

    public function slow(): static
    {
        return $this->state(['duration_ms' => 5000]);
    }
}
