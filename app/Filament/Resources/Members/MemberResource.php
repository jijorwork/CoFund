<?php

namespace App\Filament\Resources\Members;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Membership\Actions\ActivateMember;
use App\Domain\Membership\Actions\ExitMember;
use App\Domain\Membership\Actions\LinkMemberToUser;
use App\Domain\Membership\Actions\MarkMemberDeceased;
use App\Domain\Membership\Actions\MarkMemberInactive;
use App\Domain\Membership\Actions\ReinstateMember;
use App\Domain\Membership\Actions\SuspendMember;
use App\Domain\Membership\Actions\UnlinkMemberFromUser;
use App\Domain\Membership\Enums\MemberStatus;
use App\Domain\Membership\Exceptions\InvalidMembershipOperation;
use App\Domain\Membership\Models\Member;
use App\Domain\Organisations\Models\OrganisationUser;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Domain\Wallets\Services\CalculateMemberWalletBalances;
use App\Filament\Resources\Members\Actions\EditMemberProfileAction;
use App\Filament\Resources\Members\Actions\RegisterMemberAction;
use App\Filament\Resources\Members\Pages\ListMembers;
use App\Filament\Resources\Members\Pages\ViewMember;
use App\Filament\Resources\Members\RelationManagers\ContactsRelationManager;
use App\Filament\Resources\Members\RelationManagers\ContributionEnrolmentsRelationManager;
use App\Filament\Resources\Members\RelationManagers\DocumentsRelationManager;
use App\Filament\Resources\Members\RelationManagers\LoansRelationManager;
use App\Filament\Resources\TenantResource;
use App\Filament\Support\FormatsMoney;
use App\Filament\Support\MaskedPersonalData;
use App\Models\User;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MemberResource extends TenantResource
{
    protected static ?string $model = Member::class;

    protected static string $viewPermission = FoundationPermission::MembersView->value;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static string|\UnitEnum|null $navigationGroup = 'Members';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'membership_number';

    /** @return Builder<Member> */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<Member> $query */
        $query = parent::getEloquentQuery();

        return $query
            ->with(['branch', 'user'])
            ->withCount([
                'contacts',
                'documents',
                'groupMemberships',
                'contributionEnrolments',
                'wallets',
                'loans',
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Member profile')
                ->schema([
                    TextEntry::make('membership_number')->label('Member number')->copyable(),
                    TextEntry::make('name')
                        ->label('Name')
                        ->state(fn (Member $record): string => $record->fullName()),
                    TextEntry::make('status')
                        ->badge()
                        ->formatStateUsing(self::formatStatus(...))
                        ->color(fn (mixed $state): string => self::statusColor(self::status($state))),
                    TextEntry::make('branch.name')->placeholder('Organisation-wide'),
                    TextEntry::make('joined_at')->date(),
                    TextEntry::make('createdBy.name')->label('Registered by')->placeholder('Legacy record'),
                    TextEntry::make('public_id')->label('Member UUID')->copyable(),
                ])
                ->columns(['md' => 2]),
            Section::make('Contact')
                ->description('Personal contact details remain masked in the organisation panel.')
                ->schema([
                    TextEntry::make('phone')
                        ->formatStateUsing(MaskedPersonalData::phone(...))
                        ->placeholder('Not provided'),
                    TextEntry::make('alternate_phone')
                        ->formatStateUsing(MaskedPersonalData::phone(...))
                        ->placeholder('Not provided'),
                    TextEntry::make('email')
                        ->formatStateUsing(MaskedPersonalData::email(...))
                        ->placeholder('Not provided'),
                ])
                ->columns(['md' => 3]),
            Section::make('Login account')
                ->description(
                    'A member record remains separate from the user login. '
                    .'Only active organisation users can be linked.',
                )
                ->schema([
                    TextEntry::make('user.name')
                        ->label('Linked user')
                        ->placeholder('No login account linked'),
                    TextEntry::make('user.email')
                        ->label('Login email')
                        ->formatStateUsing(MaskedPersonalData::email(...))
                        ->placeholder('No login account linked'),
                ])
                ->columns(['md' => 2]),
            Section::make('Activity summary')
                ->schema([
                    TextEntry::make('contacts_count')->label('Contacts')->numeric(),
                    TextEntry::make('documents_count')->label('Documents')->numeric(),
                    TextEntry::make('group_memberships_count')->label('Group memberships')->numeric(),
                    TextEntry::make('contribution_enrolments_count')->label('Contribution enrolments')->numeric(),
                    TextEntry::make('wallets_count')->label('Wallets')->numeric(),
                    TextEntry::make('loans_count')->label('Loans')->numeric(),
                ])
                ->columns(['sm' => 2, 'lg' => 3]),
            Section::make('Wallet balances')
                ->description('Balances are derived from posted ledger entries.')
                ->visible(fn (): bool => self::canPerform(FoundationPermission::WalletsView))
                ->schema([
                    RepeatableEntry::make('wallet_balances')
                        ->hiddenLabel()
                        ->state(fn (Member $record): array => array_map(
                            fn (array $row): array => [
                                'name' => $row['wallet']->name,
                                'balance' => FormatsMoney::format(
                                    $row['balance']->displayBalanceMinor,
                                    $row['wallet']->currency_code,
                                ),
                            ],
                            app(CalculateMemberWalletBalances::class)->calculate($record),
                        ))
                        ->table([
                            TableColumn::make('Wallet'),
                            TableColumn::make('Balance'),
                        ])
                        ->schema([
                            TextEntry::make('name'),
                            TextEntry::make('balance'),
                        ]),
                ])
                ->collapsible(),
            Section::make('Status history')
                ->schema([
                    RepeatableEntry::make('statusHistories')
                        ->hiddenLabel()
                        ->table([
                            TableColumn::make('Effective at'),
                            TableColumn::make('Status'),
                            TableColumn::make('Changed by'),
                            TableColumn::make('Reason'),
                        ])
                        ->schema([
                            TextEntry::make('effective_at')->dateTime(),
                            TextEntry::make('to_status')->formatStateUsing(self::formatStatus(...)),
                            TextEntry::make('changedBy.name')->placeholder('System'),
                            TextEntry::make('reason')->placeholder('No reason recorded'),
                        ]),
                ])
                ->collapsible(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('membership_number')
                    ->label('Member number')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->state(fn (Member $record): string => $record->fullName())
                    ->searchable(['first_name', 'middle_name', 'last_name'])
                    ->wrap(),
                TextColumn::make('branch.name')
                    ->placeholder('Organisation-wide')
                    ->toggleable()
                    ->visibleFrom('md'),
                TextColumn::make('phone')
                    ->label('Phone')
                    ->formatStateUsing(MaskedPersonalData::phone(...))
                    ->toggleable()
                    ->visibleFrom('lg'),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(self::formatStatus(...))
                    ->color(fn (mixed $state): string => self::statusColor(self::status($state))),
                TextColumn::make('joined_at')
                    ->date()
                    ->sortable()
                    ->visibleFrom('md'),
            ])
            ->filters([
                SelectFilter::make('status')->options(
                    collect(MemberStatus::cases())->mapWithKeys(
                        fn (MemberStatus $status): array => [
                            $status->value => Str::headline($status->value),
                        ],
                    )->all(),
                ),
            ])
            ->headerActions([
                RegisterMemberAction::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditMemberProfileAction::make(),
                ...self::accountLinkActions(),
                ...self::lifecycleActions(),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMembers::route('/'),
            'view' => ViewMember::route('/{record}'),
        ];
    }

    public static function getRelations(): array
    {
        return [
            ContactsRelationManager::class,
            DocumentsRelationManager::class,
            ContributionEnrolmentsRelationManager::class,
            LoansRelationManager::class,
        ];
    }

    /** @return list<Action> */
    public static function accountLinkActions(): array
    {
        return [
            Action::make('linkAccount')
                ->label('Link login account')
                ->icon('heroicon-o-link')
                ->visible(fn (Member $record): bool => $record->user_id === null
                    && ! in_array(
                        $record->status,
                        [MemberStatus::Exited, MemberStatus::Deceased],
                        true,
                    )
                    && self::canPerform(FoundationPermission::MembersLinkUser))
                ->schema([
                    Select::make('user_id')
                        ->label('Organisation user')
                        ->options(self::availableAccountOptions(...))
                        ->searchable()
                        ->preload()
                        ->required()
                        ->helperText(
                            'Only active users without another current member link are listed.',
                        ),
                ])
                ->modalDescription(
                    'This connects the member record to an existing CoFund login. '
                    .'It does not create a new user.',
                )
                ->action(function (
                    Member $record,
                    array $data,
                    LinkMemberToUser $linkMember,
                    RoleAuthorization $authorization,
                    TenantContext $tenantContext,
                ): void {
                    $authorization->authorize(FoundationPermission::MembersLinkUser->value);
                    $userId = $data['user_id'] ?? null;
                    $access = OrganisationUser::query()
                        ->active()
                        ->whereBelongsTo($tenantContext->organisation())
                        ->where('user_id', is_numeric($userId) ? (int) $userId : 0)
                        ->with('user')
                        ->first();

                    if ($access === null) {
                        throw ValidationException::withMessages([
                            'user_id' => 'Select an active user in this organisation.',
                        ]);
                    }

                    self::accountLinkOperation(
                        fn () => $linkMember->handle($record, $access->user),
                        'Login account linked',
                    );
                }),
            Action::make('unlinkAccount')
                ->label('Unlink login account')
                ->icon('heroicon-o-link-slash')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn (Member $record): bool => $record->user_id !== null
                    && self::canPerform(FoundationPermission::MembersLinkUser))
                ->action(function (
                    Member $record,
                    UnlinkMemberFromUser $unlinkMember,
                    RoleAuthorization $authorization,
                ): void {
                    $authorization->authorize(FoundationPermission::MembersLinkUser->value);
                    self::accountLinkOperation(
                        fn () => $unlinkMember->handle($record),
                        'Login account unlinked',
                    );
                }),
        ];
    }

    /** @return list<Action> */
    public static function lifecycleActions(): array
    {
        return [
            Action::make('activate')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn (Member $record): bool => $record->status === MemberStatus::Pending
                    && self::canPerform(FoundationPermission::MembersActivate))
                ->action(function (Member $record, ActivateMember $domainAction): void {
                    self::transition(
                        FoundationPermission::MembersActivate,
                        fn () => $domainAction->handle($record, self::actor()),
                    );
                }),
            Action::make('suspend')
                ->icon('heroicon-o-pause-circle')
                ->color('warning')
                ->schema([self::reasonField('Suspension reason', required: true)])
                ->visible(fn (Member $record): bool => $record->status === MemberStatus::Active
                    && self::canPerform(FoundationPermission::MembersSuspend))
                ->action(function (Member $record, array $data, SuspendMember $domainAction): void {
                    self::transition(
                        FoundationPermission::MembersSuspend,
                        fn () => $domainAction->handle($record, self::actor(), self::reason($data)),
                    );
                }),
            Action::make('reinstate')
                ->icon('heroicon-o-arrow-path')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn (Member $record): bool => in_array(
                    $record->status,
                    [MemberStatus::Suspended, MemberStatus::Inactive],
                    true,
                ) && self::canPerform(FoundationPermission::MembersSuspend))
                ->action(function (Member $record, ReinstateMember $domainAction): void {
                    self::transition(
                        FoundationPermission::MembersSuspend,
                        fn () => $domainAction->handle($record, self::actor()),
                    );
                }),
            Action::make('markInactive')
                ->label('Mark inactive')
                ->icon('heroicon-o-minus-circle')
                ->schema([self::reasonField('Reason')])
                ->visible(fn (Member $record): bool => $record->status === MemberStatus::Active
                    && self::canPerform(FoundationPermission::MembersSuspend))
                ->action(function (Member $record, array $data, MarkMemberInactive $domainAction): void {
                    self::transition(
                        FoundationPermission::MembersSuspend,
                        fn () => $domainAction->handle(
                            $record,
                            self::actor(),
                            self::optionalReason($data),
                        ),
                    );
                }),
            Action::make('exit')
                ->icon('heroicon-o-arrow-right-start-on-rectangle')
                ->color('danger')
                ->schema([
                    DatePicker::make('effective_date')
                        ->label('Exit date')
                        ->required()
                        ->maxDate(now()),
                    self::reasonField('Exit reason'),
                ])
                ->visible(fn (Member $record): bool => $record->status === MemberStatus::Active
                    && self::canPerform(FoundationPermission::MembersExit))
                ->action(function (Member $record, array $data, ExitMember $domainAction): void {
                    self::transition(
                        FoundationPermission::MembersExit,
                        fn () => $domainAction->handle(
                            $record,
                            self::actor(),
                            Date::parse(self::effectiveDate($data)),
                            self::optionalReason($data),
                        ),
                    );
                }),
            Action::make('markDeceased')
                ->label('Mark deceased')
                ->icon('heroicon-o-user-minus')
                ->color('danger')
                ->schema([
                    DatePicker::make('effective_date')
                        ->label('Date of death')
                        ->required()
                        ->maxDate(now()),
                    self::reasonField('Reason'),
                ])
                ->visible(fn (Member $record): bool => $record->status !== MemberStatus::Deceased
                    && self::canPerform(FoundationPermission::MembersExit))
                ->action(function (Member $record, array $data, MarkMemberDeceased $domainAction): void {
                    self::transition(
                        FoundationPermission::MembersExit,
                        fn () => $domainAction->handle(
                            $record,
                            self::actor(),
                            Date::parse(self::effectiveDate($data)),
                            self::optionalReason($data),
                        ),
                    );
                }),
        ];
    }

    private static function canPerform(FoundationPermission $permission): bool
    {
        return app(RoleAuthorization::class)->can($permission->value);
    }

    /** @return array<int, string> */
    private static function availableAccountOptions(): array
    {
        $organisation = app(TenantContext::class)->organisation();

        return OrganisationUser::query()
            ->active()
            ->whereBelongsTo($organisation)
            ->whereDoesntHave(
                'user.memberships',
                fn (Builder $query): Builder => $query
                    ->whereBelongsTo($organisation)
                    ->whereNotNull('active_user_link_key'),
            )
            ->with('user')
            ->orderBy('user_id')
            ->get()
            ->mapWithKeys(fn (OrganisationUser $access): array => [
                $access->user_id => trim(
                    $access->user->name.' · '.MaskedPersonalData::email($access->user->email),
                ),
            ])
            ->all();
    }

    /** @param Closure(): Member $callback */
    private static function accountLinkOperation(Closure $callback, string $successTitle): void
    {
        try {
            $callback();
        } catch (InvalidMembershipOperation $exception) {
            throw ValidationException::withMessages([
                'user_id' => $exception->getMessage() !== ''
                    ? $exception->getMessage()
                    : 'The account link operation is invalid.',
            ]);
        }

        Notification::make()->title($successTitle)->success()->send();
    }

    private static function actor(): User
    {
        return app(TenantContext::class)
            ->roleAssignment()
            ->user;
    }

    private static function reasonField(string $label, bool $required = false): Textarea
    {
        $field = Textarea::make('reason')
            ->label($label)
            ->maxLength(2000);

        return $required ? $field->required() : $field;
    }

    /** @param array<string, mixed> $data */
    private static function reason(array $data): string
    {
        $reason = self::optionalReason($data);

        if ($reason === null || trim($reason) === '') {
            throw ValidationException::withMessages(['reason' => 'A reason is required.']);
        }

        return $reason;
    }

    /** @param array<string, mixed> $data */
    private static function optionalReason(array $data): ?string
    {
        $reason = $data['reason'] ?? null;

        return is_string($reason) && trim($reason) !== '' ? trim($reason) : null;
    }

    /** @param array<string, mixed> $data */
    private static function effectiveDate(array $data): string
    {
        $date = $data['effective_date'] ?? null;

        if (! is_string($date)) {
            throw ValidationException::withMessages([
                'effective_date' => 'An effective date is required.',
            ]);
        }

        return $date;
    }

    /** @param Closure(): Member $callback */
    private static function transition(
        FoundationPermission $permission,
        Closure $callback,
    ): void {
        app(RoleAuthorization::class)->authorize($permission->value);

        try {
            $callback();
        } catch (InvalidMembershipOperation $exception) {
            throw ValidationException::withMessages([
                'reason' => $exception->getMessage(),
            ]);
        }

        Notification::make()
            ->title('Member status updated')
            ->success()
            ->send();
    }

    private static function formatStatus(mixed $state): string
    {
        return Str::headline(self::status($state)->value);
    }

    private static function status(mixed $state): MemberStatus
    {
        return $state instanceof MemberStatus
            ? $state
            : MemberStatus::from((string) $state);
    }

    private static function statusColor(MemberStatus $status): string
    {
        return match ($status) {
            MemberStatus::Active => 'success',
            MemberStatus::Pending => 'info',
            MemberStatus::Suspended, MemberStatus::Inactive => 'warning',
            MemberStatus::Exited, MemberStatus::Deceased => 'gray',
        };
    }
}
