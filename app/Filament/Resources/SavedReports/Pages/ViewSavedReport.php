<?php

namespace App\Filament\Resources\SavedReports\Pages;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Domain\Reports\Enums\ReportExportStatus;
use App\Domain\Reports\Enums\ReportRunStatus;
use App\Domain\Reports\Models\ReportExport;
use App\Domain\Reports\Models\ReportRun;
use App\Domain\Reports\Models\SavedReport;
use App\Domain\Reports\Services\ExecuteRegisteredReport;
use App\Domain\Reports\Services\NormalizeReportRows;
use App\Domain\Reports\Services\ReportRegistry;
use App\Domain\Reports\Services\ReportScope;
use App\Filament\Resources\SavedReports\SavedReportResource;
use App\Jobs\GenerateReportExport;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Throwable;

class ViewSavedReport extends ViewRecord
{
    protected static string $resource = SavedReportResource::class;

    protected string $view = 'filament.resources.saved-reports.pages.view-saved-report';

    /** @var list<string> */
    public array $previewColumns = [];

    /** @var list<array<string, string>> */
    public array $previewRows = [];

    public ?string $previewSummary = null;

    public ?string $previewPeriod = null;

    public ?string $previewScopeKey = null;

    public bool $previewTruncated = false;

    public function canDisplayPreview(): bool
    {
        $context = app(TenantContext::class);

        return $this->previewScopeKey !== null
            && $this->previewScopeKey === $context->roleAssignment()->public_id
            && SavedReportResource::canView($this->getRecord());
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('runReport')
                ->label('Run report')
                ->icon('heroicon-o-play')
                ->visible(fn (): bool => $this->canRunReport())
                ->action(function (): void {
                    $authorization = app(RoleAuthorization::class);
                    $authorization->authorize(FoundationPermission::FilamentReportsRun->value);
                    $context = app(TenantContext::class);
                    $report = SavedReportResource::getEloquentQuery()->whereKey($this->getRecord()->getKey())->firstOrFail();
                    $definition = app(ReportRegistry::class)->get($report->report_key);
                    $authorization->authorize($definition->permission);
                    if ($definition->exportFormats === []) {
                        throw ValidationException::withMessages(['report_key' => ['This report cannot be run from the panel.']]);
                    }

                    $filters = $report->filters;
                    $branch = app(ReportScope::class)->branch($filters['branch_uuid'] ?? null);
                    if ($branch !== null) {
                        $filters['branch_uuid'] = $branch->public_id;
                    }
                    $started = hrtime(true);
                    $run = ReportRun::query()->create([
                        'organisation_id' => $context->organisation()->getKey(),
                        'user_id' => auth()->id(),
                        'acting_role_id' => $context->role()->getKey(),
                        'saved_report_id' => $report->getKey(),
                        'report_key' => $report->report_key,
                        'filters' => $filters,
                        'status' => ReportRunStatus::Running,
                        'started_at' => now(),
                    ]);

                    try {
                        $result = app(ExecuteRegisteredReport::class)->execute($report->report_key, $filters);
                        $rows = app(NormalizeReportRows::class)->rows($result['data']);
                        $run->update([
                            'status' => ReportRunStatus::Completed,
                            'row_count' => count($rows),
                            'duration_ms' => (int) ((hrtime(true) - $started) / 1_000_000),
                            'completed_at' => now(),
                        ]);

                        $visibleRows = array_slice($rows, 0, 100);
                        $this->previewColumns = array_values(collect($visibleRows)->flatMap(fn (array $row): array => array_keys($row))->unique()->all());
                        $this->previewRows = array_map(function (array $row): array {
                            return collect($this->previewColumns)->mapWithKeys(fn (string $column): array => [
                                $column => $this->previewValue($row[$column] ?? null),
                            ])->all();
                        }, $visibleRows);
                        $this->previewSummary = json_encode($result['summary'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
                        $this->previewPeriod = $result['range']->localStart->toDateString().' to '.$result['range']->localEnd->toDateString();
                        $this->previewTruncated = count($rows) > 100;
                        $this->previewScopeKey = $context->roleAssignment()->public_id;
                        Notification::make()->title('Report ready')->success()->send();
                    } catch (InvalidArgumentException $exception) {
                        $this->failRun($run, $started);
                        throw ValidationException::withMessages(['report_key' => [$exception->getMessage()]]);
                    } catch (Throwable $exception) {
                        $this->failRun($run, $started);
                        throw $exception;
                    }
                }),
            Action::make('requestExport')
                ->label('Export CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->visible(fn (): bool => $this->canRequestExport())
                ->requiresConfirmation()
                ->action(function (): void {
                    $authorization = app(RoleAuthorization::class);
                    $authorization->authorize(FoundationPermission::ReportsExportsCreate->value);
                    $authorization->authorize(FoundationPermission::FilamentReportsExport->value);
                    $context = app(TenantContext::class);
                    $report = SavedReportResource::getEloquentQuery()->whereKey($this->getRecord()->getKey())->firstOrFail();
                    $definition = app(ReportRegistry::class)->get($report->report_key);
                    $authorization->authorize($definition->permission);
                    if (! in_array('csv', $definition->exportFormats, true)) {
                        throw ValidationException::withMessages(['report_key' => ['This report cannot be exported as CSV.']]);
                    }

                    $filters = $report->filters;
                    $branch = app(ReportScope::class)->branch($filters['branch_uuid'] ?? null);
                    if ($branch !== null) {
                        $filters['branch_uuid'] = $branch->public_id;
                    }

                    DB::transaction(function () use ($context, $report, $filters): void {
                        $export = ReportExport::query()->create([
                            'organisation_id' => $context->organisation()->getKey(),
                            'requested_by_user_id' => auth()->id(),
                            'acting_role_id' => $context->role()->getKey(),
                            'report_key' => $report->report_key,
                            'format' => 'csv',
                            'filters' => $filters,
                            'status' => ReportExportStatus::Pending,
                            'currency_code' => $context->organisation()->currency_code,
                            'requested_at' => now(),
                        ]);
                        GenerateReportExport::dispatch($export->getKey())->afterCommit();
                    });
                    Notification::make()->title('Report export requested')->success()->send();
                }),
        ];
    }

    private function canRunReport(): bool
    {
        $record = $this->getRecord();
        if (! $record instanceof SavedReport || ! SavedReportResource::canView($record)) {
            return false;
        }

        $authorization = app(RoleAuthorization::class);
        $definition = app(ReportRegistry::class)->get($record->report_key);

        return $authorization->can(FoundationPermission::FilamentReportsRun->value)
            && $authorization->can($definition->permission)
            && $definition->exportFormats !== [];
    }

    private function previewValue(mixed $value): string
    {
        if ($value === null) {
            return '—';
        }
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }
        if (is_scalar($value)) {
            return (string) $value;
        }

        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    private function failRun(ReportRun $run, int $started): void
    {
        $run->update([
            'status' => ReportRunStatus::Failed,
            'duration_ms' => (int) ((hrtime(true) - $started) / 1_000_000),
            'failed_at' => now(),
            'failure_reason' => 'The report could not be completed.',
        ]);
    }

    private function canRequestExport(): bool
    {
        $record = $this->getRecord();
        if (! $record instanceof SavedReport || ! SavedReportResource::canView($record)) {
            return false;
        }

        $authorization = app(RoleAuthorization::class);
        $definition = app(ReportRegistry::class)->get($record->report_key);

        return $authorization->can(FoundationPermission::ReportsExportsCreate->value)
            && $authorization->can(FoundationPermission::FilamentReportsExport->value)
            && $authorization->can($definition->permission)
            && in_array('csv', $definition->exportFormats, true);
    }
}
