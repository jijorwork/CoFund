<?php

namespace App\Filament\Resources\ContributionEnrolments;

use App\Domain\Contributions\Actions\ApproveContributionPlanEnrolment;
use App\Domain\Contributions\Actions\CancelContributionPlanEnrolment;
use App\Domain\Contributions\Actions\EndContributionPlanEnrolment;
use App\Domain\Contributions\Actions\EnrolMemberInContributionPlan;
use App\Domain\Contributions\Actions\ExtendContributionSchedule;
use App\Domain\Contributions\Actions\ReinstateContributionPlanEnrolment;
use App\Domain\Contributions\Actions\RejectContributionPlanEnrolment;
use App\Domain\Contributions\Actions\SuspendContributionPlanEnrolment;
use App\Domain\Contributions\Enums\ApprovalStatus;
use App\Domain\Contributions\Enums\EnrolmentStatus;
use App\Domain\Contributions\Exceptions\InvalidContributionOperation;
use App\Domain\Contributions\Models\ContributionObligation;
use App\Domain\Contributions\Models\ContributionPlan;
use App\Domain\Contributions\Models\MemberPlanEnrolment;
use App\Domain\Contributions\Services\ContributionScope;
use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Membership\Enums\MemberStatus;
use App\Domain\Membership\Models\Member;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Filament\Resources\ContributionEnrolments\Pages\ListContributionEnrolments;
use App\Filament\Resources\ContributionEnrolments\Pages\ViewContributionEnrolment;
use App\Filament\Resources\TenantResource;
use App\Filament\Support\FormatsMoney;
use BackedEnum;
use Carbon\CarbonImmutable;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ContributionEnrolmentResource extends TenantResource
{
    protected static ?string $model = MemberPlanEnrolment::class;

    protected static string $viewPermission = FoundationPermission::ContributionEnrolmentsView->value;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static string|\UnitEnum|null $navigationGroup = 'Contributions';

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'public_id';

    protected static bool $isGloballySearchable = true;

    /** @return list<string> */
    public static function getGloballySearchableAttributes(): array
    {
        return [
            'public_id',
            'member.membership_number',
            'contributionPlan.name',
            'contributionPlan.code',
        ];
    }

    /** @return Builder<MemberPlanEnrolment> */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<MemberPlanEnrolment> $query */
        $query = parent::getEloquentQuery();

        return $query
            ->with([
                'contributionPlan.organisation',
                'member',
                'branch',
                'group',
                'createdBy',
                'approvedBy',
            ])
            ->withCount(['obligations', 'payments']);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Enrolment')
                ->schema([
                    TextEntry::make('member.membership_number')
                        ->label('Member number')
                        ->copyable(),
                    TextEntry::make('member_name')
                        ->label('Member')
                        ->state(fn (MemberPlanEnrolment $record): string => $record
                            ->member
                            ->fullName()),
                    TextEntry::make('contributionPlan.name')->label('Plan'),
                    TextEntry::make('status')
                        ->badge()
                        ->formatStateUsing(self::formatState(...)),
                    TextEntry::make('approval_status')
                        ->label('Approval')
                        ->badge()
                        ->formatStateUsing(self::formatState(...)),
                    TextEntry::make('branch.name')->placeholder('Organisation-wide'),
                    TextEntry::make('group.name')->placeholder('No group'),
                    TextEntry::make('selected_amount_minor')
                        ->label('Selected amount')
                        ->state(fn (MemberPlanEnrolment $record): string => self::moneyOrDash(
                            $record->selected_amount_minor,
                            $record->currency_code,
                        )),
                    TextEntry::make('start_date')->date(),
                    TextEntry::make('end_date')->date()->placeholder('No fixed end'),
                    TextEntry::make('next_due_date')->date()->placeholder('No next due date'),
                    TextEntry::make('createdBy.name')->label('Enrolled by'),
                    TextEntry::make('approvedBy.name')
                        ->label('Approved by')
                        ->placeholder('Not approved'),
                    TextEntry::make('public_id')
                        ->label('Enrolment UUID')
                        ->copyable()
                        ->columnSpanFull(),
                ])
                ->columns(['md' => 2, 'lg' => 3]),
            Section::make('Contribution position')
                ->schema([
                    TextEntry::make('position_status')
                        ->label('Position')
                        ->badge()
                        ->formatStateUsing(self::formatState(...)),
                    TextEntry::make('position_expected_minor')
                        ->label('Expected to date')
                        ->state(fn (MemberPlanEnrolment $record): string => self::moneyOrDash(
                            $record->getAttribute('position_expected_minor'),
                            $record->currency_code,
                        )),
                    TextEntry::make('position_satisfied_minor')
                        ->label('Satisfied to date')
                        ->state(fn (MemberPlanEnrolment $record): string => self::moneyOrDash(
                            $record->getAttribute('position_satisfied_minor'),
                            $record->currency_code,
                        )),
                    TextEntry::make('position_outstanding_minor')
                        ->label('Outstanding to date')
                        ->state(fn (MemberPlanEnrolment $record): string => self::moneyOrDash(
                            $record->getAttribute('position_outstanding_minor'),
                            $record->currency_code,
                        )),
                    TextEntry::make('position_overdue_count')->label('Overdue items')->numeric(),
                    TextEntry::make('position_upcoming_count')->label('Upcoming items')->numeric(),
                    TextEntry::make('position_next_due_date')
                        ->label('Next due date')
                        ->date()
                        ->placeholder('None'),
                    TextEntry::make('position_next_amount_minor')
                        ->label('Next expected amount')
                        ->state(fn (MemberPlanEnrolment $record): string => self::moneyOrDash(
                            $record->getAttribute('position_next_amount_minor'),
                            $record->currency_code,
                        )),
                ])
                ->columns(['sm' => 2, 'lg' => 4]),
            Section::make('Schedule')
                ->description('Generated obligations are read-only. Payments update their satisfaction state.')
                ->schema([
                    RepeatableEntry::make('obligations')
                        ->hiddenLabel()
                        ->table([
                            TableColumn::make('#'),
                            TableColumn::make('Due'),
                            TableColumn::make('Expected'),
                            TableColumn::make('Outstanding'),
                            TableColumn::make('Schedule'),
                            TableColumn::make('Satisfaction'),
                        ])
                        ->schema([
                            TextEntry::make('sequence_number'),
                            TextEntry::make('due_date')->date(),
                            TextEntry::make('expected_amount_minor')
                                ->state(fn (
                                    ContributionObligation $record,
                                ): string => self::moneyOrDash(
                                    $record->expected_amount_minor,
                                    $record->currency_code,
                                )),
                            TextEntry::make('outstanding_amount_minor')
                                ->state(fn (
                                    ContributionObligation $record,
                                ): string => self::moneyOrDash(
                                    $record->outstanding_amount_minor,
                                    $record->currency_code,
                                )),
                            TextEntry::make('schedule_status')->formatStateUsing(self::formatState(...)),
                            TextEntry::make('satisfaction_status')->formatStateUsing(self::formatState(...)),
                        ]),
                ])
                ->collapsible(),
            Section::make('Lifecycle notes')
                ->schema([
                    TextEntry::make('suspension_reason')->placeholder('Not suspended'),
                    TextEntry::make('end_reason')->placeholder('Not ended'),
                ])
                ->columns(['md' => 2])
                ->collapsed(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('member.membership_number')
                    ->label('Member number')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('member_name')
                    ->label('Member')
                    ->state(fn (MemberPlanEnrolment $record): string => $record
                        ->member
                        ->fullName())
                    ->wrap(),
                TextColumn::make('contributionPlan.name')
                    ->label('Plan')
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                TextColumn::make('selected_amount_minor')
                    ->label('Amount')
                    ->state(fn (MemberPlanEnrolment $record): string => self::moneyOrDash(
                        $record->selected_amount_minor,
                        $record->currency_code,
                    ))
                    ->visibleFrom('lg'),
                TextColumn::make('branch.name')
                    ->placeholder('Organisation-wide')
                    ->visibleFrom('lg'),
                TextColumn::make('start_date')->date()->sortable()->visibleFrom('md'),
                TextColumn::make('next_due_date')
                    ->date()
                    ->placeholder('None')
                    ->sortable()
                    ->visibleFrom('md'),
                TextColumn::make('obligations_count')
                    ->label('Schedule')
                    ->numeric()
                    ->visibleFrom('xl'),
                TextColumn::make('approval_status')
                    ->label('Approval')
                    ->badge()
                    ->formatStateUsing(self::formatState(...))
                    ->visibleFrom('md'),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(self::formatState(...)),
            ])
            ->filters([
                SelectFilter::make('status')->options(self::statusOptions()),
                SelectFilter::make('approval_status')->options(self::approvalOptions()),
            ])
            ->headerActions([self::enrolAction()])
            ->recordActions([
                ViewAction::make(),
                ...self::lifecycleActions(),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListContributionEnrolments::route('/'),
            'view' => ViewContributionEnrolment::route('/{record}'),
        ];
    }

    /** @return list<Action> */
    public static function lifecycleActions(): array
    {
        return [
            Action::make('approve')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn (
                    MemberPlanEnrolment $record,
                    RoleAuthorization $authorization,
                ): bool => $record->status === EnrolmentStatus::Pending
                    && $record->approval_status === ApprovalStatus::Pending
                    && $authorization->can(
                        FoundationPermission::ContributionEnrolmentsApprove->value,
                    ))
                ->action(function (
                    MemberPlanEnrolment $record,
                    ApproveContributionPlanEnrolment $approve,
                    RoleAuthorization $authorization,
                    TenantContext $tenantContext,
                ): void {
                    self::operate(
                        $authorization,
                        FoundationPermission::ContributionEnrolmentsApprove,
                        fn (): MemberPlanEnrolment => $approve->handle(
                            $record,
                            $tenantContext->roleAssignment()->user,
                        ),
                        'Enrolment approved',
                    );
                }),
            Action::make('reject')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->schema([self::reasonField('Rejection reason')])
                ->visible(fn (
                    MemberPlanEnrolment $record,
                    RoleAuthorization $authorization,
                ): bool => $record->status === EnrolmentStatus::Pending
                    && $record->approval_status === ApprovalStatus::Pending
                    && $authorization->can(
                        FoundationPermission::ContributionEnrolmentsApprove->value,
                    ))
                ->action(function (
                    MemberPlanEnrolment $record,
                    array $data,
                    RejectContributionPlanEnrolment $reject,
                    RoleAuthorization $authorization,
                    TenantContext $tenantContext,
                ): void {
                    self::operate(
                        $authorization,
                        FoundationPermission::ContributionEnrolmentsApprove,
                        fn (): MemberPlanEnrolment => $reject->handle(
                            $record,
                            $tenantContext->roleAssignment()->user,
                            self::optionalReason($data),
                        ),
                        'Enrolment rejected',
                    );
                }),
            Action::make('cancel')
                ->icon('heroicon-o-no-symbol')
                ->color('danger')
                ->schema([self::reasonField('Cancellation reason')])
                ->visible(fn (
                    MemberPlanEnrolment $record,
                    RoleAuthorization $authorization,
                ): bool => $record->status === EnrolmentStatus::Pending
                    && $authorization->can(
                        FoundationPermission::ContributionEnrolmentsEnd->value,
                    ))
                ->action(function (
                    MemberPlanEnrolment $record,
                    array $data,
                    CancelContributionPlanEnrolment $cancel,
                    RoleAuthorization $authorization,
                    TenantContext $tenantContext,
                ): void {
                    self::operate(
                        $authorization,
                        FoundationPermission::ContributionEnrolmentsEnd,
                        fn (): MemberPlanEnrolment => $cancel->handle(
                            $record,
                            $tenantContext->roleAssignment()->user,
                            self::optionalReason($data),
                        ),
                        'Enrolment cancelled',
                    );
                }),
            Action::make('suspend')
                ->icon('heroicon-o-pause-circle')
                ->color('warning')
                ->schema([self::reasonField('Suspension reason', required: true)])
                ->visible(fn (
                    MemberPlanEnrolment $record,
                    RoleAuthorization $authorization,
                ): bool => $record->status === EnrolmentStatus::Active
                    && $authorization->can(
                        FoundationPermission::ContributionEnrolmentsSuspend->value,
                    ))
                ->action(function (
                    MemberPlanEnrolment $record,
                    array $data,
                    SuspendContributionPlanEnrolment $suspend,
                    RoleAuthorization $authorization,
                    TenantContext $tenantContext,
                ): void {
                    self::operate(
                        $authorization,
                        FoundationPermission::ContributionEnrolmentsSuspend,
                        fn (): MemberPlanEnrolment => $suspend->handle(
                            $record,
                            $tenantContext->roleAssignment()->user,
                            self::requiredReason($data),
                        ),
                        'Enrolment suspended',
                    );
                }),
            Action::make('reinstate')
                ->icon('heroicon-o-arrow-path')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn (
                    MemberPlanEnrolment $record,
                    RoleAuthorization $authorization,
                ): bool => $record->status === EnrolmentStatus::Suspended
                    && $authorization->can(
                        FoundationPermission::ContributionEnrolmentsSuspend->value,
                    ))
                ->action(function (
                    MemberPlanEnrolment $record,
                    ReinstateContributionPlanEnrolment $reinstate,
                    RoleAuthorization $authorization,
                ): void {
                    self::operate(
                        $authorization,
                        FoundationPermission::ContributionEnrolmentsSuspend,
                        fn (): MemberPlanEnrolment => $reinstate->handle($record),
                        'Enrolment reinstated',
                    );
                }),
            Action::make('end')
                ->icon('heroicon-o-stop-circle')
                ->color('danger')
                ->schema([
                    DatePicker::make('effective_date')
                        ->label('End date')
                        ->required()
                        ->maxDate(now()),
                    self::reasonField('End reason', required: true),
                ])
                ->visible(fn (
                    MemberPlanEnrolment $record,
                    RoleAuthorization $authorization,
                ): bool => in_array(
                    $record->status,
                    [EnrolmentStatus::Active, EnrolmentStatus::Suspended],
                    true,
                ) && $authorization->can(
                    FoundationPermission::ContributionEnrolmentsEnd->value,
                ))
                ->action(function (
                    MemberPlanEnrolment $record,
                    array $data,
                    EndContributionPlanEnrolment $end,
                    RoleAuthorization $authorization,
                    TenantContext $tenantContext,
                ): void {
                    $effectiveDate = $data['effective_date'] ?? null;

                    if (! is_string($effectiveDate)) {
                        throw ValidationException::withMessages([
                            'effective_date' => 'An end date is required.',
                        ]);
                    }

                    self::operate(
                        $authorization,
                        FoundationPermission::ContributionEnrolmentsEnd,
                        fn (): MemberPlanEnrolment => $end->handle(
                            $record,
                            $tenantContext->roleAssignment()->user,
                            CarbonImmutable::parse($effectiveDate),
                            self::requiredReason($data),
                        ),
                        'Enrolment ended',
                    );
                }),
            Action::make('extendSchedule')
                ->label('Extend schedule')
                ->icon('heroicon-o-calendar-days')
                ->requiresConfirmation()
                ->visible(fn (
                    MemberPlanEnrolment $record,
                    RoleAuthorization $authorization,
                ): bool => $record->status === EnrolmentStatus::Active
                    && $authorization->can(
                        FoundationPermission::ContributionSchedulesGenerate->value,
                    ))
                ->action(function (
                    MemberPlanEnrolment $record,
                    ExtendContributionSchedule $extend,
                    RoleAuthorization $authorization,
                ): void {
                    self::operate(
                        $authorization,
                        FoundationPermission::ContributionSchedulesGenerate,
                        fn (): MemberPlanEnrolment => $extend->handle($record),
                        'Contribution schedule extended',
                    );
                }),
        ];
    }

    private static function enrolAction(): Action
    {
        return Action::make('enrolMember')
            ->label('Enrol member')
            ->icon('heroicon-o-plus')
            ->visible(fn (RoleAuthorization $authorization): bool => $authorization
                ->can(FoundationPermission::ContributionEnrolmentsCreate->value))
            ->schema([
                Select::make('member_id')
                    ->label('Active member')
                    ->options(fn (
                        TenantContext $tenantContext,
                        ContributionScope $scope,
                    ): array => self::memberOptions($tenantContext, $scope))
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('plan_id')
                    ->label('Active contribution plan')
                    ->options(fn (
                        TenantContext $tenantContext,
                        ContributionScope $scope,
                    ): array => self::planOptions($tenantContext, $scope))
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live(),
                TextInput::make('selected_amount_minor')
                    ->label('Selected amount (minor units)')
                    ->helperText('Required only for member-selected plans.')
                    ->numeric()
                    ->integer()
                    ->minValue(1)
                    ->visible(fn (
                        Get $get,
                        TenantContext $tenantContext,
                        ContributionScope $scope,
                    ): bool => self::selectedPlanNeedsAmount(
                        $get('plan_id'),
                        $tenantContext,
                        $scope,
                    )),
                DatePicker::make('start_date')->required(),
                DatePicker::make('end_date')->rule('after_or_equal:start_date'),
            ])
            ->action(function (
                array $data,
                EnrolMemberInContributionPlan $enrol,
                RoleAuthorization $authorization,
                TenantContext $tenantContext,
                ContributionScope $scope,
            ): void {
                $authorization->authorize(
                    FoundationPermission::ContributionEnrolmentsCreate->value,
                );
                $startDate = $data['start_date'] ?? null;

                if (! is_string($startDate)) {
                    throw ValidationException::withMessages([
                        'start_date' => 'A start date is required.',
                    ]);
                }

                $endDate = $data['end_date'] ?? null;

                try {
                    $member = self::resolveMember(
                        $data['member_id'] ?? null,
                        $tenantContext,
                        $scope,
                    );
                    $plan = self::resolvePlan(
                        $data['plan_id'] ?? null,
                        $tenantContext,
                        $scope,
                    );
                    $enrol->handle(
                        $tenantContext->roleAssignment()->user,
                        $member,
                        $plan,
                        is_numeric($data['selected_amount_minor'] ?? null)
                            ? (int) $data['selected_amount_minor']
                            : null,
                        CarbonImmutable::parse($startDate),
                        is_string($endDate) && $endDate !== ''
                            ? CarbonImmutable::parse($endDate)
                            : null,
                    );
                } catch (InvalidContributionOperation $exception) {
                    throw self::operationException($exception);
                }

                Notification::make()->title('Member enrolled')->success()->send();
            });
    }

    /** @return array<int, string> */
    private static function memberOptions(
        TenantContext $tenantContext,
        ContributionScope $scope,
    ): array {
        $query = Member::query()
            ->whereBelongsTo($tenantContext->organisation())
            ->where('status', MemberStatus::Active);
        $scope->applyEnrolmentAccess($query);

        return $query
            ->orderBy('membership_number')
            ->get()
            ->mapWithKeys(fn (Member $member): array => [
                $member->getKey() => $member->membership_number.' · '.$member->fullName(),
            ])
            ->all();
    }

    /** @return array<int, string> */
    private static function planOptions(
        TenantContext $tenantContext,
        ContributionScope $scope,
    ): array {
        $query = ContributionPlan::query()
            ->whereBelongsTo($tenantContext->organisation())
            ->where('status', 'active');
        $scope->applyPlanAccess($query);

        return $query
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (ContributionPlan $plan): array => [
                $plan->getKey() => $plan->code.' · '.$plan->name,
            ])
            ->all();
    }

    private static function resolveMember(
        mixed $memberId,
        TenantContext $tenantContext,
        ContributionScope $scope,
    ): Member {
        $query = Member::query()
            ->whereBelongsTo($tenantContext->organisation())
            ->where('status', MemberStatus::Active)
            ->whereKey(is_numeric($memberId) ? (int) $memberId : 0);
        $scope->applyEnrolmentAccess($query);

        return $query->first()
            ?? throw new InvalidContributionOperation('The selected member is unavailable.');
    }

    private static function resolvePlan(
        mixed $planId,
        TenantContext $tenantContext,
        ContributionScope $scope,
    ): ContributionPlan {
        $query = ContributionPlan::query()
            ->whereBelongsTo($tenantContext->organisation())
            ->where('status', 'active')
            ->whereKey(is_numeric($planId) ? (int) $planId : 0);
        $scope->applyPlanAccess($query);

        return $query->first()
            ?? throw new InvalidContributionOperation('The selected plan is unavailable.');
    }

    private static function selectedPlanNeedsAmount(
        mixed $planId,
        TenantContext $tenantContext,
        ContributionScope $scope,
    ): bool {
        if (! is_numeric($planId)) {
            return false;
        }

        $query = ContributionPlan::query()
            ->whereBelongsTo($tenantContext->organisation())
            ->whereKey((int) $planId)
            ->where('amount_type', 'member_selected');
        $scope->applyPlanAccess($query);

        return $query->exists();
    }

    /** @return array<string, string> */
    private static function statusOptions(): array
    {
        return self::enumOptions(EnrolmentStatus::cases());
    }

    /** @return array<string, string> */
    private static function approvalOptions(): array
    {
        return self::enumOptions(ApprovalStatus::cases());
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

    private static function moneyOrDash(mixed $minorUnits, string $currencyCode): string
    {
        return is_numeric($minorUnits)
            ? FormatsMoney::format((int) $minorUnits, $currencyCode)
            : '—';
    }

    private static function reasonField(string $label, bool $required = false): Textarea
    {
        $field = Textarea::make('reason')->label($label)->maxLength(2000);

        return $required ? $field->required() : $field;
    }

    /** @param array<string, mixed> $data */
    private static function optionalReason(array $data): ?string
    {
        $reason = $data['reason'] ?? null;

        return is_string($reason) && trim($reason) !== '' ? trim($reason) : null;
    }

    /** @param array<string, mixed> $data */
    private static function requiredReason(array $data): string
    {
        $reason = self::optionalReason($data);

        if ($reason === null) {
            throw ValidationException::withMessages(['reason' => 'A reason is required.']);
        }

        return $reason;
    }

    /** @param Closure(): MemberPlanEnrolment $callback */
    private static function operate(
        RoleAuthorization $authorization,
        FoundationPermission $permission,
        Closure $callback,
        string $successTitle,
    ): void {
        $authorization->authorize($permission->value);

        try {
            $callback();
        } catch (InvalidContributionOperation $exception) {
            throw self::operationException($exception);
        }

        Notification::make()->title($successTitle)->success()->send();
    }

    private static function operationException(
        InvalidContributionOperation $exception,
    ): ValidationException {
        return ValidationException::withMessages([
            'reason' => $exception->getMessage() !== ''
                ? $exception->getMessage()
                : 'The contribution enrolment operation is invalid.',
        ]);
    }
}
