<?php

namespace App\Filament\Resources\Groups;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Membership\Actions\CreateGroup;
use App\Domain\Membership\Actions\UpdateGroup;
use App\Domain\Membership\Enums\GroupStatus;
use App\Domain\Membership\Enums\GroupType;
use App\Domain\Membership\Exceptions\InvalidMembershipOperation;
use App\Domain\Membership\Models\Group;
use App\Domain\Organisations\Models\Branch;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Filament\Resources\Groups\Pages\ListGroups;
use App\Filament\Resources\Groups\Pages\ViewGroup;
use App\Filament\Resources\Groups\RelationManagers\MembershipsRelationManager;
use App\Filament\Resources\TenantResource;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Unique;
use Illuminate\Validation\ValidationException;

class GroupResource extends TenantResource
{
    protected static ?string $model = Group::class;

    protected static string $viewPermission = FoundationPermission::GroupsView->value;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static string|\UnitEnum|null $navigationGroup = 'Members';

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'name';

    protected static bool $isGloballySearchable = true;

    /** @return list<string> */
    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'code'];
    }

    /** @return Builder<Group> */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<Group> $query */
        $query = parent::getEloquentQuery();

        return $query
            ->with(['branch', 'createdBy'])
            ->withCount([
                'memberships as active_members_count' => fn (Builder $query): Builder => $query
                    ->where('status', 'active'),
                'contributionPlans',
                'contributionEnrolments',
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Group details')
                ->schema([
                    TextEntry::make('name'),
                    TextEntry::make('code'),
                    TextEntry::make('group_type')
                        ->label('Type')
                        ->formatStateUsing(self::formatState(...)),
                    TextEntry::make('status')
                        ->badge()
                        ->formatStateUsing(self::formatState(...)),
                    TextEntry::make('branch.name')->placeholder('Organisation-wide'),
                    TextEntry::make('createdBy.name')->label('Created by'),
                    TextEntry::make('description')
                        ->placeholder('No description')
                        ->columnSpanFull(),
                    TextEntry::make('public_id')
                        ->label('Group UUID')
                        ->copyable()
                        ->columnSpanFull(),
                ])
                ->columns(['md' => 2]),
            Section::make('Activity summary')
                ->schema([
                    TextEntry::make('active_members_count')->label('Active members')->numeric(),
                    TextEntry::make('contribution_plans_count')->label('Contribution plans')->numeric(),
                    TextEntry::make('contribution_enrolments_count')->label('Enrolments')->numeric(),
                ])
                ->columns(['sm' => 3]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable()->weight('medium'),
                TextColumn::make('code')->searchable()->sortable(),
                TextColumn::make('group_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(self::formatState(...)),
                TextColumn::make('branch.name')
                    ->placeholder('Organisation-wide')
                    ->visibleFrom('md'),
                TextColumn::make('active_members_count')
                    ->label('Members')
                    ->numeric()
                    ->sortable()
                    ->visibleFrom('md'),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(self::formatState(...)),
            ])
            ->filters([
                SelectFilter::make('group_type')->options(self::typeOptions()),
                SelectFilter::make('status')->options(self::statusOptions()),
            ])
            ->headerActions([
                Action::make('createGroup')
                    ->label('Create group')
                    ->icon('heroicon-o-plus')
                    ->visible(fn (RoleAuthorization $authorization): bool => $authorization
                        ->can(FoundationPermission::GroupsCreate->value))
                    ->schema([
                        ...self::profileSchema(),
                        Select::make('branch_id')
                            ->label('Branch')
                            ->placeholder('Organisation-wide')
                            ->options(fn (TenantContext $tenantContext): array => self::branchOptions($tenantContext))
                            ->default(fn (TenantContext $tenantContext): ?int => $tenantContext->branch()?->getKey())
                            ->disabled(fn (TenantContext $tenantContext): bool => $tenantContext->branch() !== null)
                            ->dehydrated(),
                    ])
                    ->action(function (
                        array $data,
                        CreateGroup $createGroup,
                        RoleAuthorization $authorization,
                        TenantContext $tenantContext,
                    ): void {
                        $authorization->authorize(FoundationPermission::GroupsCreate->value);

                        try {
                            $createGroup->handle(
                                $tenantContext->roleAssignment()->user,
                                self::profileAttributes($data),
                                self::resolveBranch($data['branch_id'] ?? null, $tenantContext),
                            );
                        } catch (InvalidMembershipOperation $exception) {
                            throw ValidationException::withMessages([
                                'code' => $exception->getMessage(),
                            ]);
                        }

                        Notification::make()->title('Group created')->success()->send();
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('editProfile')
                    ->label('Edit profile')
                    ->icon('heroicon-o-pencil-square')
                    ->visible(fn (
                        Group $record,
                        RoleAuthorization $authorization,
                    ): bool => static::canView($record)
                        && $authorization->can(FoundationPermission::GroupsUpdate->value))
                    ->fillForm(fn (Group $record): array => $record->only([
                        'name',
                        'code',
                        'group_type',
                        'description',
                    ]))
                    ->schema(fn (Group $record): array => self::profileSchema($record))
                    ->modalDescription('Group branch and status are managed separately.')
                    ->action(function (
                        Group $record,
                        array $data,
                        UpdateGroup $updateGroup,
                        RoleAuthorization $authorization,
                    ): void {
                        $authorization->authorize(FoundationPermission::GroupsUpdate->value);

                        try {
                            $updateGroup->handle(
                                $record,
                                self::profileAttributes($data),
                                $record->branch,
                            );
                        } catch (InvalidMembershipOperation $exception) {
                            throw ValidationException::withMessages([
                                'code' => $exception->getMessage(),
                            ]);
                        }

                        Notification::make()->title('Group profile updated')->success()->send();
                    }),
            ])
            ->defaultSort('name')
            ->paginated([10, 25, 50]);
    }

    /** @return list<class-string> */
    public static function getRelations(): array
    {
        return [MembershipsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGroups::route('/'),
            'view' => ViewGroup::route('/{record}'),
        ];
    }

    /** @return array<string, string> */
    private static function typeOptions(): array
    {
        return collect(GroupType::cases())
            ->mapWithKeys(fn (GroupType $type): array => [
                $type->value => Str::headline($type->value),
            ])
            ->all();
    }

    /** @return array<string, string> */
    private static function statusOptions(): array
    {
        return collect(GroupStatus::cases())
            ->mapWithKeys(fn (GroupStatus $status): array => [
                $status->value => Str::headline($status->value),
            ])
            ->all();
    }

    private static function formatState(mixed $state): string
    {
        return Str::headline((string) ($state instanceof BackedEnum ? $state->value : $state));
    }

    /**
     * @return list<TextInput|Select|Textarea>
     */
    private static function profileSchema(?Group $ignoredGroup = null): array
    {
        return [
            TextInput::make('name')->required()->maxLength(255),
            TextInput::make('code')
                ->required()
                ->maxLength(100)
                ->unique(
                    table: Group::class,
                    column: 'code',
                    ignorable: $ignoredGroup,
                    modifyRuleUsing: fn (
                        Unique $rule,
                        TenantContext $tenantContext,
                    ): Unique => $rule->where(
                        'organisation_id',
                        $tenantContext->organisation()->getKey(),
                    ),
                ),
            Select::make('group_type')
                ->label('Type')
                ->options(self::typeOptions())
                ->enum(GroupType::class)
                ->required(),
            Textarea::make('description')->maxLength(2000)->columnSpanFull(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function profileAttributes(array $data): array
    {
        return Arr::only($data, [
            'name',
            'code',
            'group_type',
            'description',
        ]);
    }

    /** @return array<int, string> */
    private static function branchOptions(TenantContext $context): array
    {
        $branch = $context->branch();

        if ($branch !== null) {
            return [$branch->getKey() => $branch->name];
        }

        return Branch::query()
            ->active()
            ->whereBelongsTo($context->organisation())
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    private static function resolveBranch(mixed $branchId, TenantContext $context): ?Branch
    {
        if ($branchId === null || $branchId === '') {
            if ($context->branch() !== null) {
                throw new InvalidMembershipOperation('The active branch is required.');
            }

            return null;
        }

        if (! is_int($branchId) && ! (is_string($branchId) && ctype_digit($branchId))) {
            throw new InvalidMembershipOperation('A valid branch is required.');
        }

        $branch = Branch::query()
            ->active()
            ->whereBelongsTo($context->organisation())
            ->whereKey($branchId)
            ->first();

        if ($branch === null || ($context->branch() !== null && ! $branch->is($context->branch()))) {
            throw new InvalidMembershipOperation(
                'The selected branch is unavailable in the active context.',
            );
        }

        return $branch;
    }
}
