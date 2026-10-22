<?php

namespace App\Filament\Resources\Notifications;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Notifications\Models\NotificationDelivery;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Filament\Resources\Notifications\Pages\ListNotificationDeliveries;
use App\Filament\Resources\TenantResource;
use BackedEnum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class NotificationDeliveryResource extends TenantResource
{
    protected static ?string $model = NotificationDelivery::class;

    protected static string $viewPermission = FoundationPermission::NotificationDeliveriesView->value;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-paper-airplane';

    protected static string|\UnitEnum|null $navigationGroup = 'Notifications';

    protected static ?int $navigationSort = 120;

    protected static ?string $navigationLabel = 'Delivery status';

    /** @return Builder<NotificationDelivery> */
    public static function getEloquentQuery(): Builder
    {
        $context = app(TenantContext::class);
        $branch = $context->branch();

        return NotificationDelivery::query()
            ->where('organisation_id', $context->organisation()->getKey())
            ->when(
                $branch,
                fn (Builder $query) => $query->whereHas(
                    'dispatch',
                    fn (Builder $query) => $query->where('branch_id', $branch->getKey()),
                ),
            )
            ->with('dispatch');
    }

    public static function canView(Model $record): bool
    {
        if (! $record instanceof NotificationDelivery || ! app(RoleAuthorization::class)->can(static::$viewPermission)) {
            return false;
        }

        $context = app(TenantContext::class);
        $branch = $context->branch();

        return $record->organisation_id === $context->organisation()->getKey()
            && (
                $branch === null
                || $record->dispatch->branch_id === $branch->getKey()
            );
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('dispatch.event_key')->label('Event')->searchable()->wrap(),
                TextColumn::make('channel')
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): string => str((string) ($state instanceof BackedEnum ? $state->value : $state))->headline()->toString()),
                TextColumn::make('destination_masked')->label('Destination')->placeholder('Not applicable'),
                TextColumn::make('status')->badge()->searchable(),
                TextColumn::make('attempt_count')->label('Attempts')->sortable(),
                TextColumn::make('sent_at')->dateTime()->sortable()->toggleable(),
                TextColumn::make('delivered_at')->dateTime()->sortable()->toggleable(),
                TextColumn::make('failure_code')->label('Failure')->placeholder('—')->toggleable(),
                TextColumn::make('next_attempt_at')->label('Next retry')->dateTime()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('channel')->options([
                    'in_app' => 'In-app',
                    'email' => 'Email',
                    'sms' => 'SMS',
                    'messaging' => 'Messaging',
                ]),
                SelectFilter::make('status')->options([
                    'pending' => 'Pending',
                    'delivered' => 'Delivered',
                    'retry_pending' => 'Retry pending',
                    'permanently_failed' => 'Permanently failed',
                    'suppressed' => 'Suppressed',
                    'unavailable' => 'Unavailable',
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50]);
    }

    public static function getPages(): array
    {
        return ['index' => ListNotificationDeliveries::route('/')];
    }
}
