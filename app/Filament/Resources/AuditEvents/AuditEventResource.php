<?php

namespace App\Filament\Resources\AuditEvents;

use App\Domain\Identity\Auditing\Models\AuditEvent;
use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Filament\Resources\AuditEvents\Pages\ListAuditEvents;
use App\Filament\Resources\AuditEvents\Pages\ViewAuditEvent;
use App\Filament\Resources\TenantResource;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AuditEventResource extends TenantResource
{
    protected static ?string $model = AuditEvent::class;

    protected static string $viewPermission = FoundationPermission::FilamentAuditView->value;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected static string|\UnitEnum|null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 90;

    protected static ?string $recordTitleAttribute = 'event_key';

    /** @return Builder<AuditEvent> */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<AuditEvent> $query */
        $query = parent::getEloquentQuery();
        $assignment = app(TenantContext::class)->roleAssignment();

        return $query
            ->when($assignment->branch_id !== null, fn (Builder $query) => $query->where('branch_id', $assignment->branch_id))
            ->with('actor');
    }

    public static function canViewAny(): bool
    {
        return parent::canViewAny()
            && app(RoleAuthorization::class)->can(FoundationPermission::ReportsAuditView->value);
    }

    public static function canView(Model $record): bool
    {
        $assignment = app(TenantContext::class)->roleAssignment();

        return parent::canView($record)
            && ($assignment->branch_id === null || $record->getAttribute('branch_id') === $assignment->branch_id);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Audit event')
                ->schema([
                    TextEntry::make('event_key')->label('Event key'),
                    TextEntry::make('created_at')->label('Occurred at')->dateTime(),
                    TextEntry::make('actor.name')->label('Actor')->placeholder('System'),
                    TextEntry::make('subject_type')->label('Subject type'),
                    TextEntry::make('subject_public_id')->label('Subject UUID')->copyable(),
                    TextEntry::make('public_id')->label('Event UUID')->copyable(),
                    TextEntry::make('details')
                        ->state(fn (AuditEvent $record): string => json_encode($record->details ?? [], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR))
                        ->copyable()
                        ->columnSpanFull(),
                ])
                ->columns(['md' => 2]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label('Occurred')->dateTime()->sortable(),
                TextColumn::make('event_key')->label('Event')->searchable()->sortable(),
                TextColumn::make('subject_type')->label('Subject')->visibleFrom('md'),
                TextColumn::make('subject_public_id')->label('Subject UUID')->searchable()->copyable()->visibleFrom('lg'),
                TextColumn::make('actor.name')->label('Actor')->visibleFrom('lg'),
            ])
            ->recordActions([ViewAction::make()])
            ->defaultSort('id', 'desc')
            ->paginated([10, 25, 50]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAuditEvents::route('/'),
            'view' => ViewAuditEvent::route('/{record}'),
        ];
    }
}
