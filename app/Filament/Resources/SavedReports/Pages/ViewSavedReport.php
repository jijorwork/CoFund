<?php

namespace App\Filament\Resources\SavedReports\Pages;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Domain\Reports\Enums\ReportExportStatus;
use App\Domain\Reports\Models\ReportExport;
use App\Domain\Reports\Models\SavedReport;
use App\Domain\Reports\Services\ReportRegistry;
use App\Domain\Reports\Services\ReportScope;
use App\Filament\Resources\SavedReports\SavedReportResource;
use App\Jobs\GenerateReportExport;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ViewSavedReport extends ViewRecord
{
    protected static string $resource = SavedReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
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
