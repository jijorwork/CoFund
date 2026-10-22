<?php

namespace App\Filament\Resources\ReportExports;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Domain\Reports\Enums\ReportExportStatus;
use App\Domain\Reports\Models\ReportExport;
use App\Domain\Reports\Services\ReportRegistry;
use App\Filament\Resources\ReportExports\Pages\ListReportExports;
use App\Filament\Resources\ReportExports\Pages\ViewReportExport;
use App\Filament\Resources\TenantResource;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExportResource extends TenantResource
{
    protected static ?string $model = ReportExport::class;

    protected static string $viewPermission = FoundationPermission::ReportsExportsView->value;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrow-down-tray';

    protected static string|\UnitEnum|null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 131;

    protected static ?string $navigationLabel = 'Report exports';

    /** @return Builder<ReportExport> */
    public static function getEloquentQuery(): Builder
    {
        $context = app(TenantContext::class);
        $branch = $context->branch();

        return ReportExport::query()
            ->where('organisation_id', $context->organisation()->getKey())
            ->where('requested_by_user_id', auth()->id())
            ->whereIn('report_key', self::permittedReportKeys())
            ->when($branch, fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->whereNull('filters->branch_uuid')
                ->orWhere('filters->branch_uuid', $branch->public_id)));
    }

    public static function canView(Model $record): bool
    {
        if (! $record instanceof ReportExport || ! static::canViewAny()) {
            return false;
        }

        $context = app(TenantContext::class);
        $branch = $context->branch();

        return $record->organisation_id === $context->organisation()->getKey()
            && $record->requested_by_user_id === auth()->id()
            && in_array($record->report_key, self::permittedReportKeys(), true)
            && ($branch === null || ! isset($record->filters['branch_uuid']) || $record->filters['branch_uuid'] === $branch->public_id);
    }

    public static function canDownload(ReportExport $record): bool
    {
        return self::canView($record)
            && app(RoleAuthorization::class)->can(FoundationPermission::ReportsExportsDownload->value)
            && $record->status === ReportExportStatus::Completed
            && $record->storage_path !== null
            && $record->expires_at?->isFuture() === true;
    }

    public static function download(ReportExport $record): StreamedResponse
    {
        $scoped = self::getEloquentQuery()->whereKey($record->getKey())->firstOrFail();
        if (! self::canDownload($scoped) || ! Storage::disk('local')->exists($scoped->storage_path)) {
            throw ValidationException::withMessages(['export' => ['The export is not available for download.']]);
        }

        return Storage::disk('local')->download($scoped->storage_path, $scoped->file_name);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Export')->schema([
                TextEntry::make('report_key')->label('Report')->badge(),
                TextEntry::make('status')->badge(),
                TextEntry::make('format')->badge(),
                TextEntry::make('row_count')->label('Rows')->numeric()->placeholder('Pending'),
                TextEntry::make('requested_at')->dateTime(),
                TextEntry::make('completed_at')->dateTime()->placeholder('Not completed'),
                TextEntry::make('expires_at')->dateTime()->placeholder('Not available'),
                TextEntry::make('failure_reason')->placeholder('—'),
            ])->columns(['md' => 2]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('report_key')->label('Report')->badge()->searchable(),
                TextColumn::make('status')->badge(),
                TextColumn::make('format')->badge(),
                TextColumn::make('row_count')->label('Rows')->numeric()->placeholder('Pending'),
                TextColumn::make('requested_at')->dateTime()->sortable(),
                TextColumn::make('expires_at')->dateTime()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    'pending' => 'Pending',
                    'processing' => 'Processing',
                    'completed' => 'Completed',
                    'failed' => 'Failed',
                    'expired' => 'Expired',
                    'cancelled' => 'Cancelled',
                ]),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->visible(fn (ReportExport $record): bool => self::canDownload($record))
                    ->action(fn (ReportExport $record): StreamedResponse => self::download($record)),
            ])
            ->defaultSort('requested_at', 'desc')
            ->paginated([10, 25, 50]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReportExports::route('/'),
            'view' => ViewReportExport::route('/{record}'),
        ];
    }

    /** @return list<string> */
    private static function permittedReportKeys(): array
    {
        $authorization = app(RoleAuthorization::class);

        return array_keys(array_filter(
            app(ReportRegistry::class)->all(),
            fn ($definition): bool => $authorization->can($definition->permission),
        ));
    }
}
