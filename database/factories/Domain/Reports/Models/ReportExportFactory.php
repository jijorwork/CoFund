<?php

namespace Database\Factories\Domain\Reports\Models;

use App\Domain\Organisations\Models\Organisation;
use App\Domain\Reports\Models\ReportExport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ReportExport> */
class ReportExportFactory extends Factory
{
    protected $model = ReportExport::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'organisation_id' => Organisation::factory(),
            'requested_by_user_id' => User::factory(),
            'report_key' => 'trial-balance',
            'format' => 'csv',
            'filters' => [],
            'status' => 'pending',
            'currency_code' => 'GHS',
            'requested_at' => now(),
        ];
    }

    public function processing(): static
    {
        return $this->state(['status' => 'processing', 'started_at' => now()]);
    }

    public function completed(): static
    {
        return $this->state([
            'status' => 'completed',
            'file_name' => 'report.csv',
            'storage_path' => 'report-exports/report.csv',
            'row_count' => 1,
            'started_at' => now()->subMinute(),
            'completed_at' => now(),
            'expires_at' => now()->addWeek(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(['status' => 'failed', 'failed_at' => now(), 'failure_reason' => 'Safe failure reason.']);
    }

    public function expired(): static
    {
        return $this->completed()->state(['status' => 'expired', 'expires_at' => now()->subMinute()]);
    }
}
