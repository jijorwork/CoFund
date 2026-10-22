<?php

namespace App\Filament\Resources\ContributionPlans;

use App\Domain\Contributions\Actions\ActivateContributionPlan;
use App\Domain\Contributions\Actions\ArchiveContributionPlan;
use App\Domain\Contributions\Actions\CloseContributionPlan;
use App\Domain\Contributions\Actions\CreateContributionPlan;
use App\Domain\Contributions\Actions\ReinstateContributionPlan;
use App\Domain\Contributions\Actions\SuspendContributionPlan;
use App\Domain\Contributions\Actions\UpdateContributionPlan;
use App\Domain\Contributions\Enums\ContributionAmountType;
use App\Domain\Contributions\Enums\ContributionFrequency;
use App\Domain\Contributions\Enums\ContributionPlanStatus;
use App\Domain\Contributions\Enums\ContributionPlanType;
use App\Domain\Contributions\Exceptions\InvalidContributionOperation;
use App\Domain\Contributions\Models\ContributionPlan;
use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Membership\Models\Group;
use App\Domain\Organisations\Models\Branch;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Filament\Resources\ContributionPlans\Pages\ListContributionPlans;
use App\Filament\Resources\ContributionPlans\Pages\ViewContributionPlan;
use App\Filament\Resources\TenantResource;
use App\Filament\Support\FormatsMoney;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Unique;
use Illuminate\Validation\ValidationException;

class ContributionPlanResource extends TenantResource
{
    protected static ?string $model = ContributionPlan::class;

    protected static string $viewPermission = FoundationPermission::ContributionPlansView->value;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static string|\UnitEnum|null $navigationGroup = 'Contributions';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'name';

    protected static bool $isGloballySearchable = true;

    /** @return list<string> */
    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'code'];
    }

    /** @return Builder<ContributionPlan> */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<ContributionPlan> $query */
        $query = parent::getEloquentQuery();

        return $query
            ->with(['branch', 'group', 'createdBy'])
            ->withCount(['enrolments', 'obligations', 'payments']);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Contribution plan')
                ->schema([
                    TextEntry::make('name'),
                    TextEntry::make('code')->copyable(),
                    TextEntry::make('status')
                        ->badge()
                        ->formatStateUsing(self::formatState(...))
                        ->color(fn (mixed $state): string => self::statusColor(
                            self::status($state),
                        )),
                    TextEntry::make('plan_type')
                        ->label('Plan type')
                        ->formatStateUsing(self::formatState(...)),
                    TextEntry::make('branch.name')->placeholder('Organisation-wide'),
                    TextEntry::make('group.name')->placeholder('All eligible members'),
                    TextEntry::make('createdBy.name')->label('Created by'),
                    TextEntry::make('description')
                        ->placeholder('No description')
                        ->columnSpanFull(),
                    TextEntry::make('public_id')
                        ->label('Plan UUID')
                        ->copyable()
                        ->columnSpanFull(),
                ])
                ->columns(['md' => 2]),
            Section::make('Schedule and amount')
                ->schema([
                    TextEntry::make('frequency')->formatStateUsing(self::formatState(...)),
                    TextEntry::make('amount_type')
                        ->label('Amount type')
                        ->formatStateUsing(self::formatState(...)),
                    TextEntry::make('fixed_amount_minor')
                        ->label('Fixed amount')
                        ->state(fn (ContributionPlan $record): string => self::moneyOrDash(
                            $record->fixed_amount_minor,
                            $record->currency_code,
                        )),
                    TextEntry::make('minimum_amount_minor')
                        ->label('Minimum amount')
                        ->state(fn (ContributionPlan $record): string => self::moneyOrDash(
                            $record->minimum_amount_minor,
                            $record->currency_code,
                        )),
                    TextEntry::make('maximum_amount_minor')
                        ->label('Maximum amount')
                        ->state(fn (ContributionPlan $record): string => self::moneyOrDash(
                            $record->maximum_amount_minor,
                            $record->currency_code,
                        )),
                    TextEntry::make('start_date')->date()->placeholder('No fixed start'),
                    TextEntry::make('end_date')->date()->placeholder('No fixed end'),
                    TextEntry::make('collection_day_of_week')
                        ->label('Collection weekday')
                        ->formatStateUsing(self::weekday(...))
                        ->placeholder('Not applicable'),
                    TextEntry::make('collection_day_of_month')
                        ->label('Collection day')
                        ->placeholder('Not applicable'),
                    TextEntry::make('grace_period_days')
                        ->label('Grace period')
                        ->suffix(' days'),
                ])
                ->columns(['sm' => 2, 'lg' => 3]),
            Section::make('Collection rules')
                ->schema([
                    IconEntry::make('allow_partial_payments')
                        ->label('Partial payments')
                        ->boolean(),
                    IconEntry::make('allow_advance_payments')
                        ->label('Advance payments')
                        ->boolean(),
                    IconEntry::make('allow_overpayments')
                        ->label('Overpayments')
                        ->boolean(),
                    IconEntry::make('requires_approval_to_join')
                        ->label('Joining requires approval')
                        ->boolean(),
                ])
                ->columns(['sm' => 2, 'lg' => 4]),
            Section::make('Activity summary')
                ->schema([
                    TextEntry::make('enrolments_count')->label('Enrolments')->numeric(),
                    TextEntry::make('obligations_count')->label('Schedule items')->numeric(),
                    TextEntry::make('payments_count')->label('Payments')->numeric(),
                ])
                ->columns(['sm' => 3]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->wrap(),
                TextColumn::make('code')->searchable()->sortable(),
                TextColumn::make('plan_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(self::formatState(...))
                    ->visibleFrom('md'),
                TextColumn::make('frequency')
                    ->formatStateUsing(self::formatState(...))
                    ->visibleFrom('md'),
                TextColumn::make('fixed_amount_minor')
                    ->label('Amount')
                    ->state(fn (ContributionPlan $record): string => self::moneyOrDash(
                        $record->fixed_amount_minor,
                        $record->currency_code,
                    ))
                    ->visibleFrom('lg'),
                TextColumn::make('branch.name')
                    ->placeholder('Organisation-wide')
                    ->visibleFrom('lg'),
                TextColumn::make('group.name')
                    ->placeholder('All members')
                    ->visibleFrom('xl'),
                IconColumn::make('requires_approval_to_join')
                    ->label('Approval')
                    ->boolean()
                    ->visibleFrom('xl'),
                TextColumn::make('enrolments_count')
                    ->label('Enrolments')
                    ->numeric()
                    ->sortable()
                    ->visibleFrom('md'),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(self::formatState(...))
                    ->color(fn (mixed $state): string => self::statusColor(
                        self::status($state),
                    )),
            ])
            ->filters([
                SelectFilter::make('plan_type')->options(self::typeOptions()),
                SelectFilter::make('frequency')->options(self::frequencyOptions()),
                SelectFilter::make('status')->options(self::statusOptions()),
            ])
            ->headerActions([
                Action::make('createPlan')
                    ->label('Create plan')
                    ->icon('heroicon-o-plus')
                    ->visible(fn (RoleAuthorization $authorization): bool => $authorization
                        ->can(FoundationPermission::ContributionPlansCreate->value))
                    ->schema(self::configurationSchema())
                    ->action(function (
                        array $data,
                        CreateContributionPlan $createPlan,
                        RoleAuthorization $authorization,
                        TenantContext $tenantContext,
                    ): void {
                        $authorization->authorize(
                            FoundationPermission::ContributionPlansCreate->value,
                        );

                        try {
                            $createPlan->handle(
                                $tenantContext->roleAssignment()->user,
                                self::configurationAttributes(
                                    $data,
                                    $tenantContext->organisation()->currency_code,
                                ),
                                self::resolveBranch($data['branch_id'] ?? null, $tenantContext),
                                self::resolveGroup(
                                    $data['group_id'] ?? null,
                                    $tenantContext,
                                ),
                            );
                        } catch (InvalidContributionOperation $exception) {
                            throw self::configurationException($exception);
                        }

                        Notification::make()
                            ->title('Contribution plan created')
                            ->success()
                            ->send();
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
                ...self::planActions(),
            ])
            ->defaultSort('name')
            ->paginated([10, 25, 50]);
    }

    /** @return list<Action> */
    public static function planActions(): array
    {
        return [
            Action::make('editPlan')
                ->label('Edit plan')
                ->icon('heroicon-o-pencil-square')
                ->visible(fn (
                    ContributionPlan $record,
                    RoleAuthorization $authorization,
                ): bool => ! in_array(
                    $record->status,
                    [ContributionPlanStatus::Closed, ContributionPlanStatus::Archived],
                    true,
                ) && $authorization->can(
                    FoundationPermission::ContributionPlansUpdate->value,
                ))
                ->fillForm(fn (ContributionPlan $record): array => self::configurationState(
                    $record,
                ))
                ->schema(fn (ContributionPlan $record): array => self::configurationSchema(
                    $record,
                ))
                ->action(function (
                    ContributionPlan $record,
                    array $data,
                    UpdateContributionPlan $updatePlan,
                    RoleAuthorization $authorization,
                    TenantContext $tenantContext,
                ): void {
                    $authorization->authorize(
                        FoundationPermission::ContributionPlansUpdate->value,
                    );

                    try {
                        $updatePlan->handle(
                            $record,
                            self::configurationAttributes(
                                $data,
                                $record->currency_code,
                            ),
                            self::resolveBranch($data['branch_id'] ?? null, $tenantContext),
                            self::resolveGroup($data['group_id'] ?? null, $tenantContext),
                        );
                    } catch (InvalidContributionOperation $exception) {
                        throw self::configurationException($exception);
                    }

                    Notification::make()
                        ->title('Contribution plan updated')
                        ->success()
                        ->send();
                }),
            Action::make('activate')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn (
                    ContributionPlan $record,
                    RoleAuthorization $authorization,
                ): bool => $record->status === ContributionPlanStatus::Draft
                    && $authorization->can(
                        FoundationPermission::ContributionPlansActivate->value,
                    ))
                ->action(function (
                    ContributionPlan $record,
                    ActivateContributionPlan $activatePlan,
                    RoleAuthorization $authorization,
                ): void {
                    self::transition(
                        $authorization,
                        FoundationPermission::ContributionPlansActivate,
                        fn (): ContributionPlan => $activatePlan->handle($record),
                        'Contribution plan activated',
                    );
                }),
            Action::make('suspend')
                ->icon('heroicon-o-pause-circle')
                ->color('warning')
                ->requiresConfirmation()
                ->visible(fn (
                    ContributionPlan $record,
                    RoleAuthorization $authorization,
                ): bool => $record->status === ContributionPlanStatus::Active
                    && $authorization->can(
                        FoundationPermission::ContributionPlansSuspend->value,
                    ))
                ->action(function (
                    ContributionPlan $record,
                    SuspendContributionPlan $suspendPlan,
                    RoleAuthorization $authorization,
                ): void {
                    self::transition(
                        $authorization,
                        FoundationPermission::ContributionPlansSuspend,
                        fn (): ContributionPlan => $suspendPlan->handle($record),
                        'Contribution plan suspended',
                    );
                }),
            Action::make('reinstate')
                ->icon('heroicon-o-arrow-path')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn (
                    ContributionPlan $record,
                    RoleAuthorization $authorization,
                ): bool => $record->status === ContributionPlanStatus::Suspended
                    && $authorization->can(
                        FoundationPermission::ContributionPlansActivate->value,
                    ))
                ->action(function (
                    ContributionPlan $record,
                    ReinstateContributionPlan $reinstatePlan,
                    RoleAuthorization $authorization,
                ): void {
                    self::transition(
                        $authorization,
                        FoundationPermission::ContributionPlansActivate,
                        fn (): ContributionPlan => $reinstatePlan->handle($record),
                        'Contribution plan reinstated',
                    );
                }),
            Action::make('close')
                ->icon('heroicon-o-lock-closed')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn (
                    ContributionPlan $record,
                    RoleAuthorization $authorization,
                ): bool => in_array(
                    $record->status,
                    [
                        ContributionPlanStatus::Active,
                        ContributionPlanStatus::Suspended,
                    ],
                    true,
                ) && $authorization->can(
                    FoundationPermission::ContributionPlansClose->value,
                ))
                ->action(function (
                    ContributionPlan $record,
                    CloseContributionPlan $closePlan,
                    RoleAuthorization $authorization,
                ): void {
                    self::transition(
                        $authorization,
                        FoundationPermission::ContributionPlansClose,
                        fn (): ContributionPlan => $closePlan->handle($record),
                        'Contribution plan closed',
                    );
                }),
            Action::make('archive')
                ->icon('heroicon-o-archive-box')
                ->color('gray')
                ->requiresConfirmation()
                ->visible(fn (
                    ContributionPlan $record,
                    RoleAuthorization $authorization,
                ): bool => $record->status === ContributionPlanStatus::Closed
                    && $authorization->can(
                        FoundationPermission::ContributionPlansClose->value,
                    ))
                ->action(function (
                    ContributionPlan $record,
                    ArchiveContributionPlan $archivePlan,
                    RoleAuthorization $authorization,
                ): void {
                    self::transition(
                        $authorization,
                        FoundationPermission::ContributionPlansClose,
                        fn (): ContributionPlan => $archivePlan->handle($record),
                        'Contribution plan archived',
                    );
                }),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListContributionPlans::route('/'),
            'view' => ViewContributionPlan::route('/{record}'),
        ];
    }

    /**
     * Branch-restricted roles may use both their branch plans and
     * organisation-wide plans.
     *
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    protected static function scopeToActiveBranch(Builder $query, Branch $branch): Builder
    {
        return $query->where(function (Builder $query) use ($branch): void {
            $query
                ->whereNull('branch_id')
                ->orWhere('branch_id', $branch->getKey());
        });
    }

    protected static function recordMatchesActiveBranch(Model $record, Branch $branch): bool
    {
        $branchId = $record->getAttribute('branch_id');

        return $branchId === null || $branchId === $branch->getKey();
    }

    /**
     * @return list<TextInput|Select|Textarea|DatePicker|Toggle>
     */
    private static function configurationSchema(?ContributionPlan $ignoredPlan = null): array
    {
        return [
            TextInput::make('name')
                ->required()
                ->maxLength(255),
            TextInput::make('code')
                ->required()
                ->maxLength(100)
                ->unique(
                    table: ContributionPlan::class,
                    column: 'code',
                    ignorable: $ignoredPlan,
                    modifyRuleUsing: fn (
                        Unique $rule,
                        TenantContext $tenantContext,
                    ): Unique => $rule->where(
                        'organisation_id',
                        $tenantContext->organisation()->getKey(),
                    ),
                ),
            Textarea::make('description')
                ->maxLength(5000)
                ->columnSpanFull(),
            Select::make('branch_id')
                ->label('Branch')
                ->placeholder('Organisation-wide')
                ->options(fn (TenantContext $tenantContext): array => self::branchOptions(
                    $tenantContext,
                ))
                ->default(fn (TenantContext $tenantContext): ?int => $tenantContext
                    ->branch()
                    ?->getKey())
                ->disabled(fn (TenantContext $tenantContext): bool => $tenantContext
                    ->branch() !== null)
                ->dehydrated()
                ->live(),
            Select::make('group_id')
                ->label('Group')
                ->placeholder('All eligible members')
                ->options(fn (
                    Get $get,
                    TenantContext $tenantContext,
                ): array => self::groupOptions(
                    $get('branch_id'),
                    $tenantContext,
                ))
                ->searchable()
                ->preload(),
            Select::make('plan_type')
                ->label('Plan type')
                ->options(self::typeOptions())
                ->enum(ContributionPlanType::class)
                ->required(),
            Select::make('frequency')
                ->options(self::frequencyOptions())
                ->enum(ContributionFrequency::class)
                ->required()
                ->live(),
            Select::make('amount_type')
                ->label('Amount type')
                ->options(self::amountTypeOptions())
                ->enum(ContributionAmountType::class)
                ->required()
                ->live(),
            TextInput::make('fixed_amount_minor')
                ->label('Fixed amount (minor units)')
                ->numeric()
                ->integer()
                ->minValue(1)
                ->requiredIf('amount_type', ContributionAmountType::Fixed->value)
                ->visible(fn (Get $get): bool => self::enumValue($get('amount_type'))
                    === ContributionAmountType::Fixed->value),
            TextInput::make('minimum_amount_minor')
                ->label('Minimum amount (minor units)')
                ->numeric()
                ->integer()
                ->minValue(0)
                ->requiredIf(
                    'amount_type',
                    ContributionAmountType::MemberSelected->value,
                )
                ->visible(fn (Get $get): bool => in_array(
                    self::enumValue($get('amount_type')),
                    [
                        ContributionAmountType::MemberSelected->value,
                        ContributionAmountType::Flexible->value,
                    ],
                    true,
                )),
            TextInput::make('maximum_amount_minor')
                ->label('Maximum amount (minor units)')
                ->numeric()
                ->integer()
                ->minValue(1)
                ->visible(fn (Get $get): bool => in_array(
                    self::enumValue($get('amount_type')),
                    [
                        ContributionAmountType::MemberSelected->value,
                        ContributionAmountType::Flexible->value,
                    ],
                    true,
                )),
            TextInput::make('currency_code')
                ->label('Currency')
                ->default(fn (TenantContext $tenantContext): string => $tenantContext
                    ->organisation()
                    ->currency_code)
                ->disabled()
                ->dehydrated(false),
            DatePicker::make('start_date')
                ->requiredIf('frequency', ContributionFrequency::OneTime->value),
            DatePicker::make('end_date')
                ->rule('after_or_equal:start_date'),
            Select::make('collection_day_of_week')
                ->label('Collection weekday')
                ->options([
                    1 => 'Monday',
                    2 => 'Tuesday',
                    3 => 'Wednesday',
                    4 => 'Thursday',
                    5 => 'Friday',
                    6 => 'Saturday',
                    7 => 'Sunday',
                ])
                ->visible(fn (Get $get): bool => self::enumValue($get('frequency'))
                    === ContributionFrequency::Weekly->value),
            TextInput::make('collection_day_of_month')
                ->label('Collection day of month')
                ->numeric()
                ->integer()
                ->minValue(1)
                ->maxValue(28)
                ->visible(fn (Get $get): bool => self::enumValue($get('frequency'))
                    === ContributionFrequency::Monthly->value),
            TextInput::make('grace_period_days')
                ->numeric()
                ->integer()
                ->minValue(0)
                ->maxValue(365)
                ->default(0)
                ->required(),
            Toggle::make('allow_partial_payments')->default(false),
            Toggle::make('allow_advance_payments')->default(false),
            Toggle::make('allow_overpayments')->default(false),
            Toggle::make('requires_approval_to_join')->default(false),
        ];
    }

    /** @return array<string, mixed> */
    private static function configurationState(ContributionPlan $plan): array
    {
        return [
            ...$plan->only([
                'name',
                'code',
                'description',
                'fixed_amount_minor',
                'minimum_amount_minor',
                'maximum_amount_minor',
                'currency_code',
                'start_date',
                'end_date',
                'collection_day_of_week',
                'collection_day_of_month',
                'grace_period_days',
                'allow_partial_payments',
                'allow_advance_payments',
                'allow_overpayments',
                'requires_approval_to_join',
            ]),
            'branch_id' => $plan->branch_id,
            'group_id' => $plan->group_id,
            'plan_type' => $plan->plan_type->value,
            'frequency' => $plan->frequency->value,
            'amount_type' => $plan->amount_type->value,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function configurationAttributes(
        array $data,
        string $currencyCode,
    ): array {
        $attributes = Arr::only($data, [
            'name',
            'code',
            'description',
            'plan_type',
            'frequency',
            'amount_type',
            'fixed_amount_minor',
            'minimum_amount_minor',
            'maximum_amount_minor',
            'start_date',
            'end_date',
            'collection_day_of_week',
            'collection_day_of_month',
            'grace_period_days',
            'allow_partial_payments',
            'allow_advance_payments',
            'allow_overpayments',
            'requires_approval_to_join',
        ]);
        $attributes['plan_type'] = self::enumValue($attributes['plan_type'] ?? null);
        $attributes['frequency'] = self::enumValue($attributes['frequency'] ?? null);
        $attributes['amount_type'] = self::enumValue($attributes['amount_type'] ?? null);
        $amountType = $attributes['amount_type'];
        $frequency = $attributes['frequency'];

        $attributes['fixed_amount_minor'] = $amountType === ContributionAmountType::Fixed->value
            ? self::nullableInteger($attributes['fixed_amount_minor'] ?? null)
            : null;
        $attributes['minimum_amount_minor'] = $amountType !== ContributionAmountType::Fixed->value
            ? self::nullableInteger($attributes['minimum_amount_minor'] ?? null)
            : null;
        $attributes['maximum_amount_minor'] = $amountType !== ContributionAmountType::Fixed->value
            ? self::nullableInteger($attributes['maximum_amount_minor'] ?? null)
            : null;
        $attributes['collection_day_of_week'] = $frequency === ContributionFrequency::Weekly->value
            ? self::nullableInteger($attributes['collection_day_of_week'] ?? null)
            : null;
        $attributes['collection_day_of_month'] = $frequency === ContributionFrequency::Monthly->value
            ? self::nullableInteger($attributes['collection_day_of_month'] ?? null)
            : null;
        $attributes['grace_period_days'] = self::nullableInteger(
            $attributes['grace_period_days'] ?? 0,
        ) ?? 0;
        $attributes['currency_code'] = Str::upper($currencyCode);

        foreach ([
            'allow_partial_payments',
            'allow_advance_payments',
            'allow_overpayments',
            'requires_approval_to_join',
        ] as $boolean) {
            $attributes[$boolean] = (bool) ($attributes[$boolean] ?? false);
        }

        return $attributes;
    }

    /** @return array<int, string> */
    private static function branchOptions(TenantContext $tenantContext): array
    {
        $branch = $tenantContext->branch();

        if ($branch !== null) {
            return [$branch->getKey() => $branch->name];
        }

        return Branch::query()
            ->active()
            ->whereBelongsTo($tenantContext->organisation())
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /** @return array<int, string> */
    private static function groupOptions(
        mixed $branchId,
        TenantContext $tenantContext,
    ): array {
        $query = Group::query()
            ->whereBelongsTo($tenantContext->organisation())
            ->where('status', 'active');

        if (is_numeric($branchId)) {
            $query->where(function (Builder $query) use ($branchId): void {
                $query
                    ->whereNull('branch_id')
                    ->orWhere('branch_id', (int) $branchId);
            });
        } else {
            $query->whereNull('branch_id');
        }

        return $query->orderBy('name')->pluck('name', 'id')->all();
    }

    private static function resolveBranch(
        mixed $branchId,
        TenantContext $tenantContext,
    ): ?Branch {
        if ($branchId === null || $branchId === '') {
            if ($tenantContext->branch() !== null) {
                throw new InvalidContributionOperation('The active branch is required.');
            }

            return null;
        }

        if (! is_numeric($branchId)) {
            throw new InvalidContributionOperation('A valid branch is required.');
        }

        $branch = Branch::query()
            ->active()
            ->whereBelongsTo($tenantContext->organisation())
            ->whereKey((int) $branchId)
            ->first();

        if (
            $branch === null
            || (
                $tenantContext->branch() !== null
                && ! $branch->is($tenantContext->branch())
            )
        ) {
            throw new InvalidContributionOperation(
                'The selected branch is unavailable in the active context.',
            );
        }

        return $branch;
    }

    private static function resolveGroup(
        mixed $groupId,
        TenantContext $tenantContext,
    ): ?Group {
        if ($groupId === null || $groupId === '') {
            return null;
        }

        if (! is_numeric($groupId)) {
            throw new InvalidContributionOperation('A valid group is required.');
        }

        $group = Group::query()
            ->whereBelongsTo($tenantContext->organisation())
            ->where('status', 'active')
            ->whereKey((int) $groupId)
            ->first();

        if ($group === null) {
            throw new InvalidContributionOperation(
                'The selected group is unavailable in the active context.',
            );
        }

        return $group;
    }

    private static function nullableInteger(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    private static function enumValue(mixed $value): string
    {
        return (string) ($value instanceof BackedEnum ? $value->value : $value);
    }

    private static function configurationException(
        InvalidContributionOperation $exception,
    ): ValidationException {
        return ValidationException::withMessages([
            'code' => $exception->getMessage() !== ''
                ? $exception->getMessage()
                : 'The contribution plan configuration is invalid.',
        ]);
    }

    /** @param Closure(): ContributionPlan $callback */
    private static function transition(
        RoleAuthorization $authorization,
        FoundationPermission $permission,
        Closure $callback,
        string $successTitle,
    ): void {
        $authorization->authorize($permission->value);

        try {
            $callback();
        } catch (InvalidContributionOperation $exception) {
            throw self::configurationException($exception);
        }

        Notification::make()->title($successTitle)->success()->send();
    }

    /** @return array<string, string> */
    private static function typeOptions(): array
    {
        return self::enumOptions(ContributionPlanType::cases());
    }

    /** @return array<string, string> */
    private static function frequencyOptions(): array
    {
        return self::enumOptions(ContributionFrequency::cases());
    }

    /** @return array<string, string> */
    private static function statusOptions(): array
    {
        return self::enumOptions(ContributionPlanStatus::cases());
    }

    /** @return array<string, string> */
    private static function amountTypeOptions(): array
    {
        return self::enumOptions(ContributionAmountType::cases());
    }

    /**
     * @param  list<BackedEnum>  $cases
     * @return array<string, string>
     */
    private static function enumOptions(array $cases): array
    {
        return collect($cases)
            ->mapWithKeys(fn (BackedEnum $case): array => [
                (string) $case->value => Str::headline((string) $case->value),
            ])
            ->all();
    }

    private static function formatState(mixed $state): string
    {
        return Str::headline((string) ($state instanceof BackedEnum ? $state->value : $state));
    }

    private static function status(mixed $state): ContributionPlanStatus
    {
        return $state instanceof ContributionPlanStatus
            ? $state
            : ContributionPlanStatus::from((string) $state);
    }

    private static function statusColor(ContributionPlanStatus $status): string
    {
        return match ($status) {
            ContributionPlanStatus::Active => 'success',
            ContributionPlanStatus::Draft => 'info',
            ContributionPlanStatus::Suspended => 'warning',
            ContributionPlanStatus::Closed, ContributionPlanStatus::Archived => 'gray',
        };
    }

    private static function moneyOrDash(mixed $minorUnits, string $currencyCode): string
    {
        return is_numeric($minorUnits)
            ? FormatsMoney::format((int) $minorUnits, $currencyCode)
            : '—';
    }

    private static function weekday(mixed $day): string
    {
        return match (is_numeric($day) ? (int) $day : null) {
            1 => 'Monday',
            2 => 'Tuesday',
            3 => 'Wednesday',
            4 => 'Thursday',
            5 => 'Friday',
            6 => 'Saturday',
            7 => 'Sunday',
            default => '—',
        };
    }
}
