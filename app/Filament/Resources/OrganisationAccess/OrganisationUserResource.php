<?php

namespace App\Filament\Resources\OrganisationAccess;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Models\OrganisationUserRole;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Organisations\Actions\ChangeOrganisationAccessStatus;
use App\Domain\Organisations\Actions\GrantOrganisationAccess;
use App\Domain\Organisations\Enums\OrganisationAccessStatus;
use App\Domain\Organisations\Exceptions\InvalidOrganisationAccess;
use App\Domain\Organisations\Models\Branch;
use App\Domain\Organisations\Models\OrganisationUser;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Filament\Resources\OrganisationAccess\Pages\ListOrganisationUsers;
use App\Filament\Resources\OrganisationAccess\Pages\ViewOrganisationUser;
use App\Filament\Resources\TenantResource;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OrganisationUserResource extends TenantResource
{
    protected static ?string $model = OrganisationUser::class;

    protected static string $viewPermission = FoundationPermission::OrganisationUsersView->value;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|\UnitEnum|null $navigationGroup = 'Organisation';

    protected static ?int $navigationSort = 20;

    protected static ?string $navigationLabel = 'Organisation access';

    protected static ?string $modelLabel = 'organisation access';

    protected static ?string $pluralModelLabel = 'organisation access';

    protected static ?string $slug = 'organisation-access';

    protected static ?string $recordTitleAttribute = 'public_id';

    protected static bool $isGloballySearchable = true;

    /** @return list<string> */
    public static function getGloballySearchableAttributes(): array
    {
        return ['user.name'];
    }

    /** @return Builder<OrganisationUser> */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<OrganisationUser> $query */
        $query = parent::getEloquentQuery();

        return $query->with([
            'user',
            'grantedBy',
            'statusHistories.changedBy',
            'statusHistories.actingRole',
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Access details')
                ->schema([
                    TextEntry::make('user.name')->label('Person'),
                    TextEntry::make('user.public_id')->label('User UUID')->copyable(),
                    TextEntry::make('status')
                        ->badge()
                        ->formatStateUsing(static::formatStatus(...))
                        ->color(fn (mixed $state): string => match (static::status($state)) {
                            OrganisationAccessStatus::Active => 'success',
                            OrganisationAccessStatus::Invited => 'info',
                            OrganisationAccessStatus::Suspended => 'warning',
                            OrganisationAccessStatus::Revoked => 'gray',
                        }),
                    TextEntry::make('joined_at')->dateTime()->placeholder('Not joined'),
                    TextEntry::make('grantedBy.name')->label('Granted by')->placeholder('System or legacy record'),
                    TextEntry::make('created_at')->label('Granted at')->dateTime(),
                    TextEntry::make('public_id')->label('Access UUID')->copyable(),
                ])
                ->columns(['md' => 2]),
            Section::make('Status history')
                ->schema([
                    RepeatableEntry::make('statusHistories')
                        ->hiddenLabel()
                        ->table([
                            TableColumn::make('Changed at'),
                            TableColumn::make('Transition'),
                            TableColumn::make('Changed by'),
                            TableColumn::make('Reason'),
                        ])
                        ->schema([
                            TextEntry::make('effective_at')->dateTime(),
                            TextEntry::make('to_status')
                                ->formatStateUsing(fn (mixed $state): string => Str::headline(
                                    static::status($state)->value,
                                )),
                            TextEntry::make('changedBy.name'),
                            TextEntry::make('reason')->placeholder('No reason recorded'),
                        ]),
                ])
                ->collapsible(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('user.name')
            ->columns([
                TextColumn::make('user.name')->label('Person')->searchable()->sortable()->weight('medium'),
                TextColumn::make('user.public_id')
                    ->label('User UUID')
                    ->copyable()
                    ->limit(12)
                    ->visibleFrom('md'),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(static::formatStatus(...))
                    ->color(fn (mixed $state): string => match (static::status($state)) {
                        OrganisationAccessStatus::Active => 'success',
                        OrganisationAccessStatus::Invited => 'info',
                        OrganisationAccessStatus::Suspended => 'warning',
                        OrganisationAccessStatus::Revoked => 'gray',
                    }),
                TextColumn::make('joined_at')->date()->sortable()->placeholder('Not joined')->visibleFrom('lg'),
            ])
            ->filters([
                SelectFilter::make('status')->options(
                    collect(OrganisationAccessStatus::cases())
                        ->mapWithKeys(fn (OrganisationAccessStatus $status): array => [
                            $status->value => Str::headline($status->value),
                        ])
                        ->all(),
                ),
            ])
            ->headerActions([
                Action::make('grantAccess')
                    ->label('Grant access')
                    ->icon('heroicon-o-user-plus')
                    ->visible(fn (RoleAuthorization $authorization): bool => $authorization
                        ->can(FoundationPermission::OrganisationUsersManage->value))
                    ->schema([
                        TextInput::make('user_uuid')
                            ->label('User public UUID')
                            ->helperText('Enter the exact public UUID supplied by the user. CoFund does not expose a platform-wide user directory.')
                            ->required()
                            ->uuid(),
                        Select::make('status')
                            ->options([
                                OrganisationAccessStatus::Active->value => 'Active now',
                                OrganisationAccessStatus::Invited->value => 'Invited',
                            ])
                            ->default(OrganisationAccessStatus::Active->value)
                            ->rules([
                                Rule::enum(OrganisationAccessStatus::class)->only([
                                    OrganisationAccessStatus::Active,
                                    OrganisationAccessStatus::Invited,
                                ]),
                            ])
                            ->required(),
                    ])
                    ->modalHeading('Grant organisation access')
                    ->modalDescription('This grants access only to the verified active organisation.')
                    ->action(function (array $data, GrantOrganisationAccess $grantAccess): void {
                        $userUuid = $data['user_uuid'] ?? null;
                        $status = $data['status'] ?? null;

                        if (! is_string($userUuid) || ! is_string($status)) {
                            throw ValidationException::withMessages([
                                'user_uuid' => 'A valid user UUID and access status are required.',
                            ]);
                        }

                        $user = User::query()->where('public_id', $userUuid)->first();

                        if ($user === null) {
                            throw ValidationException::withMessages([
                                'user_uuid' => 'No user was found for that public UUID.',
                            ]);
                        }

                        try {
                            $grantAccess->handle($user, OrganisationAccessStatus::from($status));
                        } catch (InvalidOrganisationAccess $exception) {
                            throw ValidationException::withMessages([
                                'user_uuid' => $exception->getMessage(),
                            ]);
                        }

                        Notification::make()
                            ->title('Organisation access granted')
                            ->success()
                            ->send();
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('activate')
                    ->label('Activate')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (OrganisationUser $record): bool => self::canManageStatus($record)
                        && $record->status === OrganisationAccessStatus::Invited)
                    ->action(fn (
                        OrganisationUser $record,
                        ChangeOrganisationAccessStatus $changeStatus,
                    ) => self::changeStatus(
                        $record,
                        OrganisationAccessStatus::Active,
                        $changeStatus,
                    )),
                Action::make('suspend')
                    ->label('Suspend')
                    ->icon('heroicon-o-pause-circle')
                    ->color('warning')
                    ->schema([self::reasonField('Suspension reason')])
                    ->visible(fn (OrganisationUser $record): bool => self::canManageStatus($record)
                        && $record->status === OrganisationAccessStatus::Active)
                    ->action(fn (
                        OrganisationUser $record,
                        array $data,
                        ChangeOrganisationAccessStatus $changeStatus,
                    ) => self::changeStatus(
                        $record,
                        OrganisationAccessStatus::Suspended,
                        $changeStatus,
                        self::reason($data),
                    )),
                Action::make('reactivate')
                    ->label('Reactivate')
                    ->icon('heroicon-o-arrow-path')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (OrganisationUser $record): bool => self::canManageStatus($record)
                        && $record->status === OrganisationAccessStatus::Suspended)
                    ->action(fn (
                        OrganisationUser $record,
                        ChangeOrganisationAccessStatus $changeStatus,
                    ) => self::changeStatus(
                        $record,
                        OrganisationAccessStatus::Active,
                        $changeStatus,
                    )),
                Action::make('revoke')
                    ->label('Revoke')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->schema([self::reasonField('Revocation reason')])
                    ->visible(fn (OrganisationUser $record): bool => self::canManageStatus($record)
                        && $record->status !== OrganisationAccessStatus::Revoked)
                    ->action(fn (
                        OrganisationUser $record,
                        array $data,
                        ChangeOrganisationAccessStatus $changeStatus,
                    ) => self::changeStatus(
                        $record,
                        OrganisationAccessStatus::Revoked,
                        $changeStatus,
                        self::reason($data),
                    )),
            ])
            ->emptyStateHeading('No organisation access in this context')
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrganisationUsers::route('/'),
            'view' => ViewOrganisationUser::route('/{record}'),
        ];
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    protected static function scopeToActiveBranch(Builder $query, Branch $branch): Builder
    {
        $relevantUserIds = OrganisationUserRole::query()
            ->select('user_id')
            ->whereBelongsTo(app(TenantContext::class)->organisation())
            ->currentlyValid()
            ->where(fn (Builder $assignments): Builder => $assignments
                ->whereBelongsTo($branch)
                ->orWhereNull('branch_id'));

        return $query->whereIn('user_id', $relevantUserIds);
    }

    protected static function recordMatchesActiveBranch(Model $record, Branch $branch): bool
    {
        return OrganisationUserRole::query()
            ->whereBelongsTo(app(TenantContext::class)->organisation())
            ->where('user_id', $record->getAttribute('user_id'))
            ->currentlyValid()
            ->where(fn (Builder $query): Builder => $query
                ->whereBelongsTo($branch)
                ->orWhereNull('branch_id'))
            ->exists();
    }

    protected static function formatStatus(mixed $state): string
    {
        return Str::headline(static::status($state)->value);
    }

    protected static function status(mixed $state): OrganisationAccessStatus
    {
        return $state instanceof OrganisationAccessStatus
            ? $state
            : OrganisationAccessStatus::from((string) $state);
    }

    private static function canManageStatus(OrganisationUser $record): bool
    {
        $context = app(TenantContext::class);

        return app(RoleAuthorization::class)
            ->can(FoundationPermission::OrganisationUsersManage->value)
            && $record->organisation_id === $context->organisation()->getKey()
            && $record->user_id !== $context->roleAssignment()->user_id;
    }

    private static function reasonField(string $label): Textarea
    {
        return Textarea::make('reason')
            ->label($label)
            ->required()
            ->minLength(5)
            ->maxLength(1000);
    }

    /** @param array<string, mixed> $data */
    private static function reason(array $data): string
    {
        $reason = $data['reason'] ?? null;

        if (! is_string($reason)) {
            throw ValidationException::withMessages([
                'reason' => 'A reason is required.',
            ]);
        }

        return $reason;
    }

    private static function changeStatus(
        OrganisationUser $record,
        OrganisationAccessStatus $status,
        ChangeOrganisationAccessStatus $changeStatus,
        ?string $reason = null,
    ): void {
        try {
            $changeStatus->handle(
                access: $record,
                to: $status,
                actor: app(TenantContext::class)->roleAssignment()->user,
                reason: $reason,
            );
        } catch (InvalidOrganisationAccess $exception) {
            throw ValidationException::withMessages([
                'reason' => $exception->getMessage(),
            ]);
        }

        Notification::make()
            ->title('Organisation access updated')
            ->success()
            ->send();
    }
}
