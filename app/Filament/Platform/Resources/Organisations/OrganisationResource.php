<?php

namespace App\Filament\Platform\Resources\Organisations;

use App\Domain\Identity\Enums\SystemRole;
use App\Domain\Organisations\Actions\ChangeOrganisationStatus;
use App\Domain\Organisations\Enums\OrganisationStatus;
use App\Domain\Organisations\Models\Organisation;
use App\Filament\Platform\Resources\Organisations\Pages\ListOrganisations;
use App\Filament\Platform\Resources\Organisations\Pages\ViewOrganisation;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class OrganisationResource extends Resource
{
    protected static ?string $model = Organisation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationLabel = 'Organisation reviews';

    public static function canViewAny(): bool
    {
        return static::isPlatformAdministrator();
    }

    public static function canView(Model $record): bool
    {
        return static::isPlatformAdministrator();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    /** @return Builder<Organisation> */
    public static function getEloquentQuery(): Builder
    {
        return Organisation::query()
            ->withCount(['branches', 'members', 'organisationAccesses'])
            ->latest();
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Organisation review')
                ->schema([
                    TextEntry::make('name'),
                    TextEntry::make('code'),
                    TextEntry::make('organisation_type')
                        ->label('Type')
                        ->formatStateUsing(static::formatState(...)),
                    TextEntry::make('status')
                        ->badge()
                        ->formatStateUsing(static::formatState(...))
                        ->color(static::statusColor(...)),
                    TextEntry::make('currency_code')->label('Currency'),
                    TextEntry::make('timezone')->label('Time zone'),
                    TextEntry::make('phone')->placeholder('Not provided'),
                    TextEntry::make('email')->placeholder('Not provided'),
                    TextEntry::make('address')->placeholder('Not provided')->columnSpanFull(),
                    TextEntry::make('public_id')->label('Public UUID')->copyable()->columnSpanFull(),
                ])
                ->columns(['md' => 2]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable()->weight('medium'),
                TextColumn::make('code')->searchable(),
                TextColumn::make('organisation_type')->label('Type')->formatStateUsing(static::formatState(...)),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(static::formatState(...))
                    ->color(static::statusColor(...)),
                TextColumn::make('branches_count')->label('Branches')->numeric()->visibleFrom('md'),
                TextColumn::make('members_count')->label('Members')->numeric()->visibleFrom('lg'),
                TextColumn::make('created_at')->label('Submitted')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(
                    collect(OrganisationStatus::cases())
                        ->mapWithKeys(fn (OrganisationStatus $status): array => [
                            $status->value => Str::headline($status->value),
                        ])
                        ->all(),
                ),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('approve')
                    ->label('Approve')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Organisation $record): bool => $record->status === OrganisationStatus::Pending)
                    ->action(fn (Organisation $record): mixed => static::changeStatus(
                        $record,
                        OrganisationStatus::Active,
                    )),
                Action::make('suspend')
                    ->icon(Heroicon::OutlinedNoSymbol)
                    ->color('danger')
                    ->form([
                        Textarea::make('reason')->required()->maxLength(2000),
                    ])
                    ->requiresConfirmation()
                    ->visible(fn (Organisation $record): bool => $record->status === OrganisationStatus::Active)
                    ->action(fn (Organisation $record, array $data): mixed => static::changeStatus(
                        $record,
                        OrganisationStatus::Suspended,
                        (string) $data['reason'],
                    )),
                Action::make('reactivate')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Organisation $record): bool => in_array(
                        $record->status,
                        [OrganisationStatus::Suspended, OrganisationStatus::Inactive],
                        true,
                    ))
                    ->action(fn (Organisation $record): mixed => static::changeStatus(
                        $record,
                        OrganisationStatus::Active,
                    )),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrganisations::route('/'),
            'view' => ViewOrganisation::route('/{record}'),
        ];
    }

    protected static function isPlatformAdministrator(): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && $user->hasRole(SystemRole::PlatformAdmin->value, 'web');
    }

    protected static function changeStatus(
        Organisation $organisation,
        OrganisationStatus $status,
        ?string $reason = null,
    ): Organisation {
        $user = auth()->user();

        abort_unless($user instanceof User, 403);

        app(ChangeOrganisationStatus::class)->handle($user, $organisation, $status, $reason);

        return $organisation->refresh();
    }

    protected static function formatState(mixed $state): string
    {
        return Str::headline((string) ($state instanceof BackedEnum ? $state->value : $state));
    }

    protected static function statusColor(mixed $state): string
    {
        $status = $state instanceof OrganisationStatus
            ? $state
            : OrganisationStatus::from((string) $state);

        return match ($status) {
            OrganisationStatus::Active => 'success',
            OrganisationStatus::Pending => 'warning',
            OrganisationStatus::Suspended => 'danger',
            OrganisationStatus::Inactive => 'gray',
        };
    }
}
