<?php

namespace App\Filament\Resources\Notifications;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Notifications\Models\NotificationTemplate;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Filament\Resources\Notifications\Pages\ListNotificationTemplates;
use App\Filament\Resources\TenantResource;
use BackedEnum;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class NotificationTemplateResource extends TenantResource
{
    protected static ?string $model = NotificationTemplate::class;

    protected static string $viewPermission = FoundationPermission::NotificationTemplatesView->value;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static string|\UnitEnum|null $navigationGroup = 'Notifications';

    protected static ?int $navigationSort = 121;

    /** @return Builder<NotificationTemplate> */
    public static function getEloquentQuery(): Builder
    {
        $organisationId = app(TenantContext::class)->organisation()->getKey();

        return NotificationTemplate::query()
            ->where(fn (Builder $query) => $query
                ->whereNull('organisation_id')
                ->orWhere('organisation_id', $organisationId));
    }

    public static function canView(Model $record): bool
    {
        return $record instanceof NotificationTemplate
            && app(RoleAuthorization::class)->can(static::$viewPermission)
            && in_array(
                $record->organisation_id,
                [null, app(TenantContext::class)->organisation()->getKey()],
                true,
            );
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->wrap(),
                TextColumn::make('event_key')->label('Event')->searchable(),
                TextColumn::make('channel')
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): string => str((string) ($state instanceof BackedEnum ? $state->value : $state))->headline()->toString()),
                TextColumn::make('locale')->badge(),
                TextColumn::make('version')->sortable(),
                TextColumn::make('status')->badge(),
                IconColumn::make('is_system_default')->label('System')->boolean(),
                TextColumn::make('activated_at')->dateTime()->toggleable(),
                TextColumn::make('retired_at')->dateTime()->toggleable(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->paginated([10, 25, 50]);
    }

    public static function getPages(): array
    {
        return ['index' => ListNotificationTemplates::route('/')];
    }
}
