<?php

namespace App\Filament\Resources\SavedReports;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Domain\Reports\Enums\SavedReportVisibility;
use App\Domain\Reports\Models\SavedReport;
use App\Domain\Reports\Services\ReportRegistry;
use App\Filament\Resources\SavedReports\Pages\ListSavedReports;
use App\Filament\Resources\SavedReports\Pages\ViewSavedReport;
use App\Filament\Resources\TenantResource;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SavedReportResource extends TenantResource
{
    protected static ?string $model = SavedReport::class;

    protected static string $viewPermission = FoundationPermission::ReportsView->value;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static string|\UnitEnum|null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 130;

    protected static ?string $navigationLabel = 'Saved reports';

    protected static ?string $recordTitleAttribute = 'name';

    /** @return Builder<SavedReport> */
    public static function getEloquentQuery(): Builder
    {
        $context = app(TenantContext::class);
        $branch = $context->branch();

        return SavedReport::query()
            ->where('organisation_id', $context->organisation()->getKey())
            ->whereIn('report_key', self::permittedReportKeys())
            ->where(fn (Builder $query) => $query
                ->where('owner_user_id', auth()->id())
                ->orWhere('visibility', SavedReportVisibility::Organisation->value)
                ->orWhere(fn (Builder $query) => $query
                    ->where('visibility', SavedReportVisibility::Role->value)
                    ->where('acting_role_id', $context->role()->getKey())))
            ->when($branch, fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->whereNull('filters->branch_uuid')
                ->orWhere('filters->branch_uuid', $branch->public_id)))
            ->with('owner');
    }

    public static function canView(Model $record): bool
    {
        if (! $record instanceof SavedReport || ! static::canViewAny()) {
            return false;
        }

        $context = app(TenantContext::class);
        $branch = $context->branch();
        $visibility = $record->owner_user_id === auth()->id()
            || $record->visibility === SavedReportVisibility::Organisation
            || ($record->visibility === SavedReportVisibility::Role && $record->acting_role_id === $context->role()->getKey());

        return $record->organisation_id === $context->organisation()->getKey()
            && in_array($record->report_key, self::permittedReportKeys(), true)
            && $visibility
            && ($branch === null || ! isset($record->filters['branch_uuid']) || $record->filters['branch_uuid'] === $branch->public_id);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Report definition')->schema([
                TextEntry::make('name'),
                TextEntry::make('description')->placeholder('No description'),
                TextEntry::make('report_key')->label('Report type')->badge(),
                TextEntry::make('visibility')->badge(),
                TextEntry::make('owner.name')->label('Owner'),
                TextEntry::make('updated_at')->dateTime(),
            ])->columns(['md' => 2]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('report_key')->label('Report type')->badge()->searchable(),
                TextColumn::make('visibility')->badge(),
                TextColumn::make('owner.name')->label('Owner'),
                TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('visibility')->options([
                    'private' => 'Private',
                    'role' => 'Role',
                    'organisation' => 'Organisation',
                ]),
            ])
            ->recordActions([ViewAction::make()])
            ->defaultSort('updated_at', 'desc')
            ->paginated([10, 25, 50]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSavedReports::route('/'),
            'view' => ViewSavedReport::route('/{record}'),
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
