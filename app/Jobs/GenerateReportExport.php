<?php

namespace App\Jobs;

use App\Domain\Identity\Models\OrganisationUserRole;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Domain\Reports\Enums\ReportExportStatus;
use App\Domain\Reports\Models\ReportExport;
use App\Domain\Reports\Services\ExecuteRegisteredReport;
use App\Domain\Reports\Services\ReportRegistry;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Throwable;

class GenerateReportExport implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $reportExportId) {}

    public function handle(
        TenantContext $context,
        ExecuteRegisteredReport $executor,
        ReportRegistry $registry,
        RoleAuthorization $authorization,
    ): void {
        $export = ReportExport::query()->with(['organisation', 'actingRole'])->findOrFail($this->reportExportId);
        if ($export->status !== ReportExportStatus::Pending || $export->expires_at?->isPast()) {
            return;
        }

        $assignment = OrganisationUserRole::query()
            ->where('organisation_id', $export->organisation_id)
            ->where('user_id', $export->requested_by_user_id)
            ->where('role_id', $export->acting_role_id)
            ->with(['branch', 'role'])
            ->get()
            ->first(fn (OrganisationUserRole $assignment): bool => $assignment->isCurrentlyValid());
        if ($assignment === null) {
            $export->update([
                'status' => ReportExportStatus::Failed,
                'failed_at' => now(),
                'failure_reason' => 'The reporting role is no longer active.',
            ]);

            return;
        }

        $context->setOrganisation($export->organisation);
        $context->setBranch($assignment->branch);
        $context->setRoleAssignment($assignment);
        $authorization->authorize($registry->get($export->report_key)->permission);
        $export->update(['status' => ReportExportStatus::Processing, 'started_at' => now()]);
        $result = $executor->execute($export->report_key, $export->filters);
        $rows = $this->rows($result['data']);
        $stream = fopen('php://temp', 'w+');
        if ($stream === false) {
            throw new \RuntimeException('Unable to create export stream.');
        }
        if ($rows !== []) {
            fputcsv($stream, array_keys($rows[0]), escape: '');
            foreach ($rows as $row) {
                fputcsv($stream, array_map(
                    fn (mixed $value): string|int|float|null => match (true) {
                        is_array($value) => json_encode($value, JSON_THROW_ON_ERROR),
                        is_bool($value) => (int) $value,
                        is_string($value), is_int($value), is_float($value), $value === null => $value,
                        default => (string) $value,
                    },
                    $row,
                ), escape: '');
            }
        }
        rewind($stream);
        $contents = stream_get_contents($stream);
        fclose($stream);
        if ($contents === false) {
            throw new \RuntimeException('Unable to read export stream.');
        }
        $export->refresh();
        if ($export->status !== ReportExportStatus::Processing) {
            return;
        }
        $path = 'report-exports/'.$export->organisation_id.'/'.$export->public_id.'.csv';
        Storage::disk('local')->put($path, $contents);
        $export->update([
            'status' => ReportExportStatus::Completed,
            'storage_path' => $path,
            'file_name' => $export->report_key.'-'.$export->public_id.'.csv',
            'row_count' => count($rows),
            'completed_at' => now(),
            'expires_at' => now()->addDays(7),
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        ReportExport::query()->whereKey($this->reportExportId)->update([
            'status' => ReportExportStatus::Failed,
            'failed_at' => now(),
            'failure_reason' => 'The report export could not be completed.',
        ]);
    }

    /** @return list<array<string, mixed>> */
    private function rows(mixed $data): array
    {
        if (! is_array($data)) {
            return [];
        }
        if (array_is_list($data)) {
            return array_values(array_filter($data, 'is_array'));
        }

        $rows = [];
        foreach ($data as $section => $sectionRows) {
            if (! is_array($sectionRows)) {
                continue;
            }
            foreach ($sectionRows as $row) {
                if (is_array($row)) {
                    $rows[] = ['section' => $section, ...$row];
                }
            }
        }

        return $rows;
    }
}
