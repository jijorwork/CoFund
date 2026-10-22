<?php

namespace App\Filament\Resources\Branches;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Organisations\Actions\CreateBranch;
use App\Domain\Organisations\Actions\UpdateBranch;
use App\Domain\Organisations\Enums\BranchStatus;
use App\Domain\Organisations\Exceptions\BranchOutsideTenant;
use App\Domain\Organisations\Exceptions\InvalidOrganisationAccess;
use App\Domain\Organisations\Models\Branch;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Filament\Resources\Branches\Pages\ListBranches;
use App\Filament\Resources\Branches\Pages\ViewBranch;
use App\Filament\Resources\TenantResource;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
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
use Illuminate\Validation\Rules\Unique;
use Illuminate\Validation\ValidationException;

class BranchResource extends TenantResource
{
    protected static ?string $model = Branch::class;

    protected static string $viewPermission = FoundationPermission::BranchesView->value;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static string|\UnitEnum|null $navigationGroup = 'Organisation';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'name';

    protected static bool $isGloballySearchable = true;

    /** @return Builder<Branch> */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<Branch> $query */
        $query = parent::getEloquentQuery();

        return $query->withCount(['members', 'groups', 'userRoleAssignments']);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Branch details')
                    ->schema([
                        TextEntry::make('name'),
                        TextEntry::make('code'),
                        TextEntry::make('status')
                            ->badge()
                            ->formatStateUsing(static::formatStatus(...)),
                        TextEntry::make('phone')->placeholder('Not provided'),
                        TextEntry::make('email')->placeholder('Not provided'),
                        TextEntry::make('address')
                            ->placeholder('Not provided')
                            ->columnSpanFull(),
                        TextEntry::make('public_id')
                            ->label('Public UUID')
                            ->copyable()
                            ->columnSpanFull(),
                    ])
                    ->columns(['md' => 2]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),
                TextColumn::make('code')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(static::formatStatus(...))
                    ->color(fn (mixed $state): string => match (static::status($state)) {
                        BranchStatus::Active => 'success',
                        BranchStatus::Suspended => 'warning',
                        BranchStatus::Inactive => 'gray',
                    }),
                TextColumn::make('members_count')
                    ->label('Members')
                    ->numeric()
                    ->sortable()
                    ->visibleFrom('md'),
                TextColumn::make('user_role_assignments_count')
                    ->label('Staff roles')
                    ->numeric()
                    ->sortable()
                    ->visibleFrom('lg'),
                TextColumn::make('phone')
                    ->placeholder('Not provided')
                    ->toggleable()
                    ->visibleFrom('xl'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        BranchStatus::Active->value => 'Active',
                        BranchStatus::Suspended->value => 'Suspended',
                        BranchStatus::Inactive->value => 'Inactive',
                    ]),
            ])
            ->headerActions([
                Action::make('createBranch')
                    ->label('Create branch')
                    ->icon('heroicon-o-plus')
                    ->visible(fn (
                        RoleAuthorization $authorization,
                        TenantContext $tenantContext,
                    ): bool => $tenantContext->branch() === null
                        && $authorization->can(FoundationPermission::BranchesCreate->value))
                    ->schema(self::branchProfileSchema())
                    ->modalHeading('Create organisation branch')
                    ->modalDescription('The branch will belong to the verified active organisation.')
                    ->action(function (
                        array $data,
                        CreateBranch $createBranch,
                        TenantContext $tenantContext,
                    ): void {
                        try {
                            $createBranch->handle(
                                $tenantContext->organisation(),
                                self::branchAttributes($data),
                            );
                        } catch (BranchOutsideTenant|InvalidOrganisationAccess $exception) {
                            throw ValidationException::withMessages([
                                'code' => $exception->getMessage(),
                            ]);
                        }

                        Notification::make()
                            ->title('Branch created')
                            ->success()
                            ->send();
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('editProfile')
                    ->label('Edit profile')
                    ->icon('heroicon-o-pencil-square')
                    ->visible(fn (
                        Branch $record,
                        RoleAuthorization $authorization,
                    ): bool => static::canView($record)
                        && $authorization->can(FoundationPermission::BranchesUpdate->value))
                    ->fillForm(fn (Branch $record): array => $record->only([
                        'name',
                        'code',
                        'phone',
                        'email',
                        'address',
                    ]))
                    ->schema(fn (Branch $record): array => self::branchProfileSchema($record))
                    ->modalHeading('Edit branch profile')
                    ->modalDescription('Branch status is managed separately from profile details.')
                    ->action(function (
                        Branch $record,
                        array $data,
                        UpdateBranch $updateBranch,
                    ): void {
                        try {
                            $updateBranch->handle(
                                $record,
                                self::branchAttributes($data),
                            );
                        } catch (BranchOutsideTenant|InvalidOrganisationAccess $exception) {
                            throw ValidationException::withMessages([
                                'code' => $exception->getMessage(),
                            ]);
                        }

                        Notification::make()
                            ->title('Branch profile updated')
                            ->success()
                            ->send();
                    }),
            ])
            ->emptyStateHeading('No branches in this context')
            ->emptyStateDescription('Branches available to the active organisation and branch context appear here.')
            ->defaultSort('name')
            ->paginated([10, 25, 50]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBranches::route('/'),
            'view' => ViewBranch::route('/{record}'),
        ];
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    protected static function scopeToActiveBranch(Builder $query, Branch $branch): Builder
    {
        return $query->whereKey($branch->getKey());
    }

    protected static function recordMatchesActiveBranch(Model $record, Branch $branch): bool
    {
        return $record->is($branch);
    }

    protected static function formatStatus(mixed $state): string
    {
        return Str::headline(static::status($state)->value);
    }

    protected static function status(mixed $state): BranchStatus
    {
        return $state instanceof BranchStatus
            ? $state
            : BranchStatus::from((string) $state);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{
     *     name: string,
     *     code: string,
     *     phone: string|null,
     *     email: string|null,
     *     address: string|null
     * }
     */
    private static function branchAttributes(array $data): array
    {
        $name = $data['name'] ?? null;
        $code = $data['code'] ?? null;
        $phone = $data['phone'] ?? null;
        $email = $data['email'] ?? null;
        $address = $data['address'] ?? null;

        if (
            ! is_string($name)
            || trim($name) === ''
            || ! is_string($code)
            || trim($code) === ''
        ) {
            throw ValidationException::withMessages([
                'name' => 'A branch name and code are required.',
            ]);
        }

        foreach ([
            'phone' => $phone,
            'email' => $email,
            'address' => $address,
        ] as $field => $value) {
            if ($value !== null && ! is_string($value)) {
                throw ValidationException::withMessages([
                    $field => 'The value is not valid.',
                ]);
            }
        }

        return [
            'name' => trim($name),
            'code' => trim($code),
            'phone' => is_string($phone) && trim($phone) !== '' ? trim($phone) : null,
            'email' => is_string($email) && trim($email) !== '' ? trim($email) : null,
            'address' => is_string($address) && trim($address) !== '' ? trim($address) : null,
        ];
    }

    /**
     * @return list<TextInput|Textarea>
     */
    private static function branchProfileSchema(?Branch $ignoredBranch = null): array
    {
        return [
            TextInput::make('name')
                ->required()
                ->maxLength(255),
            TextInput::make('code')
                ->required()
                ->maxLength(255)
                ->unique(
                    table: Branch::class,
                    column: 'code',
                    ignorable: $ignoredBranch,
                    modifyRuleUsing: fn (
                        Unique $rule,
                        TenantContext $tenantContext,
                    ): Unique => $rule->where(
                        'organisation_id',
                        $tenantContext->organisation()->getKey(),
                    ),
                ),
            TextInput::make('phone')
                ->tel()
                ->maxLength(255),
            TextInput::make('email')
                ->email()
                ->maxLength(255),
            Textarea::make('address')
                ->maxLength(2000)
                ->columnSpanFull(),
        ];
    }
}
