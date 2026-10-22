<?php

namespace App\Filament\Resources\RoleAssignments;

use App\Domain\Identity\Actions\AssignRoleToUser;
use App\Domain\Identity\Actions\RevokeUserRole;
use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Enums\RoleAssignmentStatus;
use App\Domain\Identity\Enums\SystemRole;
use App\Domain\Identity\Exceptions\InvalidRoleAssignment;
use App\Domain\Identity\Models\OrganisationUserRole;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Organisations\Models\Branch;
use App\Domain\Organisations\Models\OrganisationUser;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Filament\Resources\RoleAssignments\Pages\ListOrganisationUserRoles;
use App\Filament\Resources\RoleAssignments\Pages\ViewOrganisationUserRole;
use App\Filament\Resources\TenantResource;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
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
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrganisationUserRoleResource extends TenantResource
{
    protected static ?string $model = OrganisationUserRole::class;

    protected static string $viewPermission = FoundationPermission::RolesView->value;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static string|\UnitEnum|null $navigationGroup = 'Organisation';

    protected static ?int $navigationSort = 30;

    protected static ?string $navigationLabel = 'Role assignments';

    protected static ?string $modelLabel = 'role assignment';

    protected static ?string $pluralModelLabel = 'role assignments';

    protected static ?string $slug = 'role-assignments';

    protected static ?string $recordTitleAttribute = 'public_id';

    protected static bool $isGloballySearchable = true;

    /** @return list<string> */
    public static function getGloballySearchableAttributes(): array
    {
        return ['user.name', 'role.name', 'branch.name'];
    }

    /** @return Builder<OrganisationUserRole> */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<OrganisationUserRole> $query */
        $query = parent::getEloquentQuery();

        return $query
            ->whereHas('role', fn (Builder $role): Builder => $role
                ->whereNot('name', SystemRole::PlatformAdmin->value))
            ->with(['user', 'role', 'branch', 'assignedBy', 'revokedBy']);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Role assignment')
                ->schema([
                    TextEntry::make('user.name')->label('Person'),
                    TextEntry::make('user.public_id')->label('User UUID')->copyable(),
                    TextEntry::make('role.name')->label('Role')->formatStateUsing(static::formatState(...)),
                    TextEntry::make('branch.name')->label('Branch')->placeholder('Organisation-wide'),
                    TextEntry::make('status')
                        ->badge()
                        ->formatStateUsing(static::formatState(...))
                        ->color(fn (mixed $state): string => match (static::status($state)) {
                            RoleAssignmentStatus::Active => 'success',
                            RoleAssignmentStatus::Pending => 'info',
                            RoleAssignmentStatus::Suspended => 'warning',
                            RoleAssignmentStatus::Revoked,
                            RoleAssignmentStatus::Expired => 'gray',
                        }),
                    TextEntry::make('starts_at')->dateTime()->placeholder('Immediately'),
                    TextEntry::make('ends_at')->dateTime()->placeholder('No end date'),
                    TextEntry::make('assignedBy.name')->label('Assigned by')->placeholder('System'),
                    TextEntry::make('assigned_at')->dateTime(),
                    TextEntry::make('revokedBy.name')->label('Revoked by')->placeholder('Not revoked'),
                    TextEntry::make('revoked_at')->dateTime()->placeholder('Not revoked'),
                    TextEntry::make('revocation_reason')->placeholder('Not revoked')->columnSpanFull(),
                    TextEntry::make('public_id')->label('Public UUID')->copyable()->columnSpanFull(),
                ])
                ->columns(['md' => 2]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')->label('Person')->searchable()->sortable()->weight('medium'),
                TextColumn::make('role.name')->label('Role')->formatStateUsing(static::formatState(...))->badge(),
                TextColumn::make('branch.name')->label('Branch')->placeholder('Organisation-wide')->visibleFrom('md'),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(static::formatState(...))
                    ->color(fn (mixed $state): string => match (static::status($state)) {
                        RoleAssignmentStatus::Active => 'success',
                        RoleAssignmentStatus::Pending => 'info',
                        RoleAssignmentStatus::Suspended => 'warning',
                        RoleAssignmentStatus::Revoked,
                        RoleAssignmentStatus::Expired => 'gray',
                    }),
                TextColumn::make('starts_at')->date()->placeholder('Immediately')->visibleFrom('lg'),
                TextColumn::make('ends_at')->date()->placeholder('No end date')->visibleFrom('xl'),
            ])
            ->filters([
                SelectFilter::make('status')->options(
                    collect(RoleAssignmentStatus::cases())
                        ->mapWithKeys(fn (RoleAssignmentStatus $status): array => [
                            $status->value => Str::headline($status->value),
                        ])
                        ->all(),
                ),
                SelectFilter::make('branch_id')
                    ->label('Branch')
                    ->options(static::branchOptions(...)),
            ])
            ->headerActions([
                Action::make('assignRole')
                    ->label('Assign role')
                    ->icon('heroicon-o-user-plus')
                    ->visible(fn (RoleAuthorization $authorization): bool => $authorization
                        ->can(FoundationPermission::RolesAssign->value))
                    ->schema([
                        Select::make('user_id')
                            ->label('Organisation user')
                            ->options(static::activeOrganisationUserOptions(...))
                            ->searchable()
                            ->required(),
                        Select::make('role_id')
                            ->label('Role')
                            ->options(static::organisationRoleOptions(...))
                            ->searchable()
                            ->required(),
                        Select::make('branch_id')
                            ->label('Branch')
                            ->options(static::branchOptions(...))
                            ->placeholder('Organisation-wide')
                            ->default(fn (TenantContext $tenantContext): ?int => $tenantContext->branch()?->getKey())
                            ->disabled(fn (TenantContext $tenantContext): bool => $tenantContext->branch() !== null)
                            ->dehydrated(),
                        DateTimePicker::make('starts_at')
                            ->label('Starts at')
                            ->seconds(false),
                        DateTimePicker::make('ends_at')
                            ->label('Ends at')
                            ->seconds(false)
                            ->after('starts_at'),
                    ])
                    ->modalHeading('Assign organisation role')
                    ->modalDescription('Platform administrator is never assignable from an organisation context.')
                    ->action(static::assignRole(...)),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('revoke')
                    ->label('Revoke')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->visible(fn (OrganisationUserRole $record, RoleAuthorization $authorization, TenantContext $tenantContext): bool => $record->status !== RoleAssignmentStatus::Revoked
                        && ! $tenantContext->roleAssignment()->is($record)
                        && $authorization->can(FoundationPermission::RolesRevoke->value))
                    ->schema([
                        Textarea::make('reason')
                            ->label('Revocation reason')
                            ->required()
                            ->minLength(10)
                            ->maxLength(500),
                    ])
                    ->requiresConfirmation()
                    ->modalHeading('Revoke role assignment')
                    ->modalDescription('The historical assignment remains available for audit.')
                    ->action(static::revokeRole(...)),
            ])
            ->emptyStateHeading('No role assignments in this context')
            ->defaultSort('assigned_at', 'desc')
            ->paginated([10, 25, 50]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrganisationUserRoles::route('/'),
            'view' => ViewOrganisationUserRole::route('/{record}'),
        ];
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    protected static function scopeToActiveBranch(Builder $query, Branch $branch): Builder
    {
        return $query->where(fn (Builder $query): Builder => $query
            ->whereBelongsTo($branch)
            ->orWhereNull('branch_id'));
    }

    protected static function recordMatchesActiveBranch(Model $record, Branch $branch): bool
    {
        return $record->getAttribute('branch_id') === null
            || $record->getAttribute('branch_id') === $branch->getKey();
    }

    protected static function formatState(mixed $state): string
    {
        return Str::headline((string) ($state instanceof BackedEnum ? $state->value : $state));
    }

    protected static function status(mixed $state): RoleAssignmentStatus
    {
        return $state instanceof RoleAssignmentStatus
            ? $state
            : RoleAssignmentStatus::from((string) $state);
    }

    /**
     * @return array<int, string>
     */
    protected static function activeOrganisationUserOptions(TenantContext $tenantContext): array
    {
        return OrganisationUser::query()
            ->whereBelongsTo($tenantContext->organisation())
            ->active()
            ->with('user')
            ->get()
            ->mapWithKeys(fn (OrganisationUser $access): array => [
                $access->user_id => sprintf(
                    '%s — %s',
                    $access->user->name,
                    Str::limit($access->user->public_id, 12, ''),
                ),
            ])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    protected static function organisationRoleOptions(): array
    {
        $roleNames = collect(SystemRole::cases())
            ->filter(fn (SystemRole $role): bool => $role->isOrganisationAssignable())
            ->map(fn (SystemRole $role): string => $role->value)
            ->all();

        return Role::query()
            ->whereIn('name', $roleNames)
            ->orderBy('name')
            ->get()
            ->mapWithKeys(function (Role $role): array {
                $systemRole = SystemRole::tryFrom($role->name);

                return [$role->getKey() => $systemRole?->label() ?? Str::headline($role->name)];
            })
            ->all();
    }

    /**
     * @return array<int, string>
     */
    protected static function branchOptions(TenantContext $tenantContext): array
    {
        return Branch::query()
            ->forOrganisation($tenantContext->organisation())
            ->active()
            ->when(
                $tenantContext->branch(),
                fn (Builder $query, Branch $branch): Builder => $query->whereKey($branch->getKey()),
            )
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected static function assignRole(
        array $data,
        AssignRoleToUser $assignRole,
        TenantContext $tenantContext,
    ): void {
        $actor = Auth::user();
        $userId = static::integerId($data['user_id'] ?? null);
        $roleId = static::integerId($data['role_id'] ?? null);
        $branchId = static::integerId($data['branch_id'] ?? null, nullable: true);

        if (! $actor instanceof User || $userId === null || $roleId === null) {
            throw ValidationException::withMessages([
                'user_id' => 'A valid organisation user and role are required.',
            ]);
        }

        $targetUser = OrganisationUser::query()
            ->whereBelongsTo($tenantContext->organisation())
            ->active()
            ->where('user_id', $userId)
            ->with('user')
            ->first()
            ?->user;
        $role = Role::query()
            ->whereKey($roleId)
            ->whereIn(
                'name',
                collect(SystemRole::cases())
                    ->filter(fn (SystemRole $systemRole): bool => $systemRole->isOrganisationAssignable())
                    ->map(fn (SystemRole $systemRole): string => $systemRole->value),
            )
            ->first();
        $branch = $branchId === null
            ? null
            : Branch::query()
                ->forOrganisation($tenantContext->organisation())
                ->when(
                    $tenantContext->branch(),
                    fn (Builder $query, Branch $activeBranch): Builder => $query->whereKey($activeBranch->getKey()),
                )
                ->whereKey($branchId)
                ->first();

        if ($targetUser === null || $role === null || ($branchId !== null && $branch === null)) {
            throw ValidationException::withMessages([
                'user_id' => 'The selected user, role, or branch is unavailable in the active context.',
            ]);
        }

        try {
            $assignRole->handle(
                assigningUser: $actor,
                organisation: $tenantContext->organisation(),
                targetUser: $targetUser,
                role: $role,
                branch: $branch,
                startsAt: is_string($data['starts_at'] ?? null) ? Date::parse($data['starts_at']) : null,
                endsAt: is_string($data['ends_at'] ?? null) ? Date::parse($data['ends_at']) : null,
            );
        } catch (InvalidRoleAssignment $exception) {
            throw ValidationException::withMessages([
                'role_id' => $exception->getMessage(),
            ]);
        }

        Notification::make()
            ->title('Role assigned')
            ->success()
            ->send();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected static function revokeRole(
        OrganisationUserRole $record,
        array $data,
        RevokeUserRole $revokeRole,
    ): void {
        $actor = Auth::user();
        $reason = $data['reason'] ?? null;

        if (! $actor instanceof User || ! is_string($reason)) {
            throw ValidationException::withMessages([
                'reason' => 'A revocation reason is required.',
            ]);
        }

        try {
            $revokeRole->handle($record, $actor, $reason);
        } catch (InvalidRoleAssignment $exception) {
            throw ValidationException::withMessages([
                'reason' => $exception->getMessage(),
            ]);
        }

        Notification::make()
            ->title('Role assignment revoked')
            ->success()
            ->send();
    }

    protected static function integerId(mixed $value, bool $nullable = false): ?int
    {
        if ($nullable && ($value === null || $value === '')) {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }

        return is_string($value) && ctype_digit($value)
            ? (int) $value
            : null;
    }
}
