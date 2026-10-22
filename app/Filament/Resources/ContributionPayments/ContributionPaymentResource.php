<?php

namespace App\Filament\Resources\ContributionPayments;

use App\Domain\Collections\Actions\RecordContributionPayment;
use App\Domain\Collections\Actions\ReverseContributionPayment;
use App\Domain\Collections\Data\ContributionAllocationData;
use App\Domain\Collections\Data\ContributionPaymentData;
use App\Domain\Collections\Data\PaymentAllocationData;
use App\Domain\Collections\Enums\ContributionPaymentMethod;
use App\Domain\Collections\Enums\ContributionPaymentStatus;
use App\Domain\Collections\Enums\PaymentAllocationStrategy;
use App\Domain\Collections\Exceptions\InvalidContributionPayment;
use App\Domain\Collections\Models\ContributionPayment;
use App\Domain\Collections\Models\ContributionPaymentAllocation;
use App\Domain\Collections\Services\AllocateContributionPayment;
use App\Domain\Collections\Services\ResolveContributionPaymentSource;
use App\Domain\Contributions\Enums\EnrolmentStatus;
use App\Domain\Contributions\Enums\ObligationStatus;
use App\Domain\Contributions\Models\ContributionObligation;
use App\Domain\Contributions\Models\MemberPlanEnrolment;
use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Enums\SystemRole;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Membership\Enums\MemberStatus;
use App\Domain\Membership\Models\Member;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Domain\Wallets\Enums\WalletOwnerType;
use App\Domain\Wallets\Enums\WalletStatus;
use App\Domain\Wallets\Enums\WalletType;
use App\Domain\Wallets\Models\Wallet;
use App\Filament\Resources\ContributionPayments\Pages\ListContributionPayments;
use App\Filament\Resources\ContributionPayments\Pages\ViewContributionPayment;
use App\Filament\Resources\TenantResource;
use App\Filament\Support\FormatsMoney;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
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

class ContributionPaymentResource extends TenantResource
{
    protected static ?string $model = ContributionPayment::class;

    protected static string $viewPermission = FoundationPermission::ContributionPaymentsView->value;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static string|\UnitEnum|null $navigationGroup = 'Contributions';

    protected static ?int $navigationSort = 30;

    protected static ?string $recordTitleAttribute = 'payment_number';

    protected static bool $isGloballySearchable = true;

    /** @return list<string> */
    public static function getGloballySearchableAttributes(): array
    {
        return [
            'payment_number',
            'public_id',
            'member.membership_number',
            'external_reference',
        ];
    }

    /** @return Builder<ContributionPayment> */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<ContributionPayment> $query */
        $query = parent::getEloquentQuery();
        $context = app(TenantContext::class);

        if ($context->role()->name === SystemRole::Collector->value) {
            $query->where('collected_by_user_id', $context->roleAssignment()->user_id);
        }

        return $query->with([
            'branch',
            'member',
            'contributionPlan',
            'enrolment',
            'financialTransaction',
            'sourceWallet',
            'destinationWallet',
            'collectedBy',
            'receipt',
            'reversedPayment',
            'reversalPayment',
        ])->withCount('allocations');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Payment')
                ->schema([
                    TextEntry::make('payment_number')->label('Payment')->copyable(),
                    TextEntry::make('status')
                        ->badge()
                        ->formatStateUsing(self::formatState(...)),
                    TextEntry::make('amount_minor')
                        ->label('Amount')
                        ->state(fn (ContributionPayment $record): string => self::money(
                            $record->amount_minor,
                            $record->currency_code,
                        )),
                    TextEntry::make('allocated_amount_minor')
                        ->label('Allocated')
                        ->state(fn (ContributionPayment $record): string => self::money(
                            $record->allocated_amount_minor,
                            $record->currency_code,
                        )),
                    TextEntry::make('unallocated_amount_minor')
                        ->label('Unallocated')
                        ->state(fn (ContributionPayment $record): string => self::money(
                            $record->unallocated_amount_minor,
                            $record->currency_code,
                        )),
                    TextEntry::make('payment_method')
                        ->label('Method')
                        ->badge()
                        ->formatStateUsing(self::formatState(...)),
                    TextEntry::make('payment_date')->date(),
                    TextEntry::make('received_at')->dateTime(),
                    TextEntry::make('external_reference')->placeholder('None'),
                    TextEntry::make('payer_name')->placeholder('Member'),
                    TextEntry::make('notes')->placeholder('No notes')->columnSpanFull(),
                    TextEntry::make('public_id')
                        ->label('Payment UUID')
                        ->copyable()
                        ->columnSpanFull(),
                ])
                ->columns(['sm' => 2, 'lg' => 3]),
            Section::make('Member and contribution')
                ->schema([
                    TextEntry::make('member.membership_number')
                        ->label('Member number')
                        ->copyable(),
                    TextEntry::make('member_name')
                        ->label('Member')
                        ->state(fn (ContributionPayment $record): string => $record
                            ->member
                            ->fullName()),
                    TextEntry::make('contributionPlan.name')->label('Plan'),
                    TextEntry::make('enrolment.public_id')
                        ->label('Enrolment UUID')
                        ->copyable(),
                    TextEntry::make('branch.name')->placeholder('Organisation-wide'),
                    TextEntry::make('collectedBy.name')->label('Collected by'),
                ])
                ->columns(['md' => 2, 'lg' => 3]),
            Section::make('Accounting trace')
                ->schema([
                    TextEntry::make('financialTransaction.transaction_number')
                        ->label('Financial transaction')
                        ->copyable(),
                    TextEntry::make('sourceWallet.wallet_number')->label('Source wallet'),
                    TextEntry::make('destinationWallet.wallet_number')->label('Destination wallet'),
                    TextEntry::make('receipt.receipt_number')
                        ->label('Receipt')
                        ->placeholder('Not issued'),
                ])
                ->columns(['md' => 2, 'lg' => 4]),
            Section::make('Allocations')
                ->description('Allocation records are immutable and may only be offset through reversal records.')
                ->schema([
                    RepeatableEntry::make('allocations')
                        ->hiddenLabel()
                        ->table([
                            TableColumn::make('#'),
                            TableColumn::make('Obligation'),
                            TableColumn::make('Due'),
                            TableColumn::make('Amount'),
                            TableColumn::make('Status'),
                            TableColumn::make('Allocated'),
                        ])
                        ->schema([
                            TextEntry::make('allocation_order'),
                            TextEntry::make('obligation.sequence_number'),
                            TextEntry::make('obligation.due_date')->date(),
                            TextEntry::make('amount_minor')
                                ->state(fn (
                                    ContributionPaymentAllocation $record,
                                ): string => self::money(
                                    $record->amount_minor,
                                    $record->currency_code,
                                )),
                            TextEntry::make('status')->formatStateUsing(self::formatState(...)),
                            TextEntry::make('allocated_at')->dateTime(),
                        ]),
                ])
                ->collapsible(),
            Section::make('Reversal trace')
                ->schema([
                    TextEntry::make('reversedPayment.payment_number')
                        ->label('Reverses payment')
                        ->placeholder('Not a reversal'),
                    TextEntry::make('reversalPayment.payment_number')
                        ->label('Reversal payment')
                        ->placeholder('Not reversed'),
                    TextEntry::make('reversal_reason')
                        ->label('Reason')
                        ->placeholder('No reversal')
                        ->columnSpanFull(),
                ])
                ->columns(['md' => 2])
                ->collapsed(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('payment_number')
                    ->label('Payment')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('member.membership_number')
                    ->label('Member')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('contributionPlan.name')
                    ->label('Plan')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('amount_minor')
                    ->label('Amount')
                    ->state(fn (ContributionPayment $record): string => self::money(
                        $record->amount_minor,
                        $record->currency_code,
                    )),
                TextColumn::make('payment_method')
                    ->label('Method')
                    ->badge()
                    ->formatStateUsing(self::formatState(...))
                    ->visibleFrom('md'),
                TextColumn::make('payment_date')
                    ->date()
                    ->sortable()
                    ->visibleFrom('md'),
                TextColumn::make('allocated_amount_minor')
                    ->label('Allocated')
                    ->state(fn (ContributionPayment $record): string => self::money(
                        $record->allocated_amount_minor,
                        $record->currency_code,
                    ))
                    ->visibleFrom('lg'),
                TextColumn::make('branch.name')
                    ->placeholder('Organisation-wide')
                    ->visibleFrom('lg'),
                TextColumn::make('collectedBy.name')
                    ->label('Collected by')
                    ->visibleFrom('xl'),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(self::formatState(...)),
            ])
            ->filters([
                SelectFilter::make('status')->options(self::statusOptions()),
                SelectFilter::make('payment_method')->options(self::methodOptions()),
            ])
            ->headerActions([self::collectAction()])
            ->recordActions([
                ViewAction::make(),
                self::reversalAction(),
            ])
            ->defaultSort('payment_date', 'desc')
            ->paginated([10, 25, 50]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListContributionPayments::route('/'),
            'view' => ViewContributionPayment::route('/{record}'),
        ];
    }

    /** @return array<string, string> */
    private static function statusOptions(): array
    {
        return collect(ContributionPaymentStatus::cases())
            ->mapWithKeys(fn (ContributionPaymentStatus $status): array => [
                $status->value => Str::headline($status->value),
            ])
            ->all();
    }

    /** @return array<string, string> */
    private static function methodOptions(): array
    {
        return collect(ContributionPaymentMethod::cases())
            ->mapWithKeys(fn (ContributionPaymentMethod $method): array => [
                $method->value => Str::headline($method->value),
            ])
            ->all();
    }

    private static function formatState(mixed $state): string
    {
        return Str::headline((string) ($state instanceof BackedEnum ? $state->value : $state));
    }

    private static function money(int $minorUnits, string $currencyCode): string
    {
        return FormatsMoney::format($minorUnits, $currencyCode);
    }

    private static function collectAction(): Action
    {
        return Action::make('collectPayment')
            ->label('Collect contribution')
            ->icon('heroicon-o-plus')
            ->visible(fn (RoleAuthorization $authorization): bool => $authorization
                ->can(FoundationPermission::ContributionPaymentsCollect->value))
            ->schema([
                Select::make('member_id')
                    ->label('Active member')
                    ->options(fn (
                        TenantContext $tenantContext,
                    ): array => self::memberOptions($tenantContext))
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live(),
                Select::make('enrolment_id')
                    ->label('Active enrolment')
                    ->options(fn (
                        Get $get,
                        TenantContext $tenantContext,
                    ): array => self::enrolmentOptions(
                        $get('member_id'),
                        $tenantContext,
                    ))
                    ->searchable()
                    ->preload()
                    ->live()
                    ->helperText('Optional only when the payment will remain unallocated.'),
                TextInput::make('amount_minor')
                    ->label('Amount (minor units)')
                    ->numeric()
                    ->integer()
                    ->minValue(1)
                    ->required()
                    ->live(onBlur: true),
                Select::make('payment_method')
                    ->label('Payment method')
                    ->options(fn (
                        RoleAuthorization $authorization,
                    ): array => self::permittedMethodOptions($authorization))
                    ->required()
                    ->live(),
                Select::make('source_wallet_id')
                    ->label('Source wallet')
                    ->options(fn (
                        Get $get,
                        TenantContext $tenantContext,
                    ): array => self::sourceWalletOptions(
                        $get('payment_method'),
                        $tenantContext,
                    ))
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('allocation_strategy')
                    ->label('Allocation')
                    ->options(fn (
                        RoleAuthorization $authorization,
                    ): array => self::allocationStrategyOptions($authorization))
                    ->default(PaymentAllocationStrategy::OldestDueFirst->value)
                    ->required()
                    ->live(),
                Repeater::make('allocations')
                    ->label('Selected obligations')
                    ->schema([
                        Select::make('obligation_id')
                            ->label('Obligation')
                            ->options(fn (
                                Get $get,
                                TenantContext $tenantContext,
                            ): array => self::obligationOptions(
                                $get('../../enrolment_id'),
                                $tenantContext,
                            ))
                            ->searchable()
                            ->required()
                            ->distinct(),
                        TextInput::make('amount_minor')
                            ->label('Amount (minor units)')
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->required(),
                    ])
                    ->columns(['md' => 2])
                    ->visible(fn (Get $get): bool => $get('allocation_strategy')
                        === PaymentAllocationStrategy::SelectedObligations->value)
                    ->defaultItems(1),
                Placeholder::make('allocation_preview')
                    ->label('Allocation preview')
                    ->content(fn (
                        Get $get,
                        TenantContext $tenantContext,
                        AllocateContributionPayment $allocator,
                    ): string => self::allocationPreview(
                        $get,
                        $tenantContext,
                        $allocator,
                    ))
                    ->columnSpanFull(),
                DatePicker::make('payment_date')
                    ->default(today())
                    ->maxDate(today())
                    ->required(),
                TextInput::make('external_reference')->maxLength(255),
                TextInput::make('payer_name')->maxLength(255),
                TextInput::make('payer_phone')->tel()->maxLength(32),
                Textarea::make('notes')->maxLength(2000)->columnSpanFull(),
                Hidden::make('idempotency_key')
                    ->default(fn (): string => (string) Str::uuid7())
                    ->required(),
            ])
            ->action(function (
                array $data,
                RecordContributionPayment $recordPayment,
                ResolveContributionPaymentSource $sources,
                RoleAuthorization $authorization,
                TenantContext $tenantContext,
            ): void {
                $authorization->authorize(
                    FoundationPermission::ContributionPaymentsCollect->value,
                );

                try {
                    $member = self::resolveMember(
                        $data['member_id'] ?? null,
                        $tenantContext,
                    );
                    $enrolment = self::resolveEnrolment(
                        $data['enrolment_id'] ?? null,
                        $member,
                        $tenantContext,
                    );
                    $method = ContributionPaymentMethod::from(
                        (string) ($data['payment_method'] ?? ''),
                    );
                    $strategy = PaymentAllocationStrategy::from(
                        (string) ($data['allocation_strategy'] ?? ''),
                    );
                    $source = self::resolveSourceWallet(
                        $data['source_wallet_id'] ?? null,
                        $tenantContext,
                    );
                    $wallets = $sources->resolve(
                        $tenantContext->roleAssignment()->user,
                        $member,
                        $method,
                        $source,
                    );
                    $paymentDate = $data['payment_date'] ?? null;

                    if (! is_string($paymentDate)) {
                        throw new InvalidContributionPayment(
                            'A payment date is required.',
                        );
                    }

                    $payment = $recordPayment->handle(
                        $tenantContext->roleAssignment()->user,
                        new ContributionPaymentData(
                            member: $member,
                            enrolment: $enrolment,
                            plan: $enrolment?->contributionPlan,
                            amountMinor: self::positiveInteger(
                                $data['amount_minor'] ?? null,
                                'A valid payment amount is required.',
                            ),
                            currencyCode: $tenantContext
                                ->organisation()
                                ->currency_code,
                            paymentMethod: $method,
                            paymentDate: CarbonImmutable::parse($paymentDate),
                            sourceWallet: $wallets['source'],
                            destinationWallet: $wallets['destination'],
                            allocationStrategy: $strategy,
                            idempotencyKey: self::requiredString(
                                $data['idempotency_key'] ?? null,
                                'An idempotency key is required.',
                            ),
                            selectedAllocations: self::resolveSelectedAllocations(
                                $strategy === PaymentAllocationStrategy::SelectedObligations
                                    ? ($data['allocations'] ?? [])
                                    : [],
                                $enrolment,
                                $tenantContext,
                            ),
                            externalReference: self::optionalString(
                                $data['external_reference'] ?? null,
                            ),
                            payerName: self::optionalString(
                                $data['payer_name'] ?? null,
                            ),
                            payerPhone: self::optionalString(
                                $data['payer_phone'] ?? null,
                            ),
                            notes: self::optionalString($data['notes'] ?? null),
                        ),
                    );
                } catch (
                    InvalidContributionPayment|\ValueError $exception
                ) {
                    throw ValidationException::withMessages([
                        'amount_minor' => $exception->getMessage() !== ''
                            ? $exception->getMessage()
                            : 'The contribution payment is invalid.',
                    ]);
                }

                Notification::make()
                    ->title('Contribution collected')
                    ->body($payment->payment_number)
                    ->success()
                    ->send();
            })
            ->modalWidth('5xl');
    }

    public static function reversalAction(): Action
    {
        return Action::make('reverse')
            ->label('Reverse')
            ->icon('heroicon-o-arrow-uturn-left')
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription(
                'A compensating payment, ledger transaction, allocation set, and receipt will be created. The original records remain immutable.',
            )
            ->schema([
                Textarea::make('reason')
                    ->label('Reversal reason')
                    ->required()
                    ->maxLength(2000),
                Hidden::make('idempotency_key')
                    ->default(fn (): string => (string) Str::uuid7())
                    ->required(),
            ])
            ->visible(fn (
                ContributionPayment $record,
                RoleAuthorization $authorization,
            ): bool => $record->status !== ContributionPaymentStatus::Reversed
                && $record->reversed_payment_id === null
                && $record->reversal_payment_id === null
                && $authorization->can(
                    FoundationPermission::ContributionPaymentsReverse->value,
                ))
            ->action(function (
                ContributionPayment $record,
                array $data,
                ReverseContributionPayment $reversePayment,
                TenantContext $tenantContext,
            ): void {
                try {
                    $reversal = $reversePayment->handle(
                        $record,
                        $tenantContext->roleAssignment()->user,
                        self::requiredString(
                            $data['reason'] ?? null,
                            'A reversal reason is required.',
                        ),
                        self::requiredString(
                            $data['idempotency_key'] ?? null,
                            'An idempotency key is required.',
                        ),
                    );
                } catch (InvalidContributionPayment $exception) {
                    throw ValidationException::withMessages([
                        'reason' => $exception->getMessage() !== ''
                            ? $exception->getMessage()
                            : 'The contribution payment cannot be reversed.',
                    ]);
                }

                Notification::make()
                    ->title('Contribution payment reversed')
                    ->body($reversal->payment_number)
                    ->success()
                    ->send();
            });
    }

    /** @return array<int, string> */
    private static function memberOptions(TenantContext $context): array
    {
        return self::memberQuery($context)
            ->orderBy('membership_number')
            ->get()
            ->mapWithKeys(fn (Member $member): array => [
                $member->getKey() => $member->membership_number.' · '.$member->fullName(),
            ])
            ->all();
    }

    /** @return Builder<Member> */
    private static function memberQuery(TenantContext $context): Builder
    {
        return Member::query()
            ->whereBelongsTo($context->organisation())
            ->where('status', MemberStatus::Active)
            ->when(
                $context->branch(),
                fn (Builder $query): Builder => $query->where(
                    'branch_id',
                    $context->branch()?->getKey(),
                ),
            );
    }

    /** @return array<int, string> */
    private static function enrolmentOptions(
        mixed $memberId,
        TenantContext $context,
    ): array {
        if (! is_numeric($memberId)) {
            return [];
        }

        return self::enrolmentQuery($context)
            ->where('member_id', (int) $memberId)
            ->with('contributionPlan')
            ->get()
            ->mapWithKeys(fn (MemberPlanEnrolment $enrolment): array => [
                $enrolment->getKey() => $enrolment->contributionPlan->code
                    .' · starts '.$enrolment->start_date->toDateString(),
            ])
            ->all();
    }

    /** @return Builder<MemberPlanEnrolment> */
    private static function enrolmentQuery(TenantContext $context): Builder
    {
        return MemberPlanEnrolment::query()
            ->whereBelongsTo($context->organisation())
            ->where('status', EnrolmentStatus::Active)
            ->when(
                $context->branch(),
                fn (Builder $query): Builder => $query->where(
                    'branch_id',
                    $context->branch()?->getKey(),
                ),
            );
    }

    /** @return array<int, string> */
    private static function obligationOptions(
        mixed $enrolmentId,
        TenantContext $context,
    ): array {
        if (! is_numeric($enrolmentId)) {
            return [];
        }

        $enrolment = self::enrolmentQuery($context)
            ->whereKey((int) $enrolmentId)
            ->first();

        if ($enrolment === null) {
            return [];
        }

        return ContributionObligation::query()
            ->whereBelongsTo($enrolment, 'enrolment')
            ->whereNotIn(
                'schedule_status',
                [ObligationStatus::Cancelled, ObligationStatus::Waived],
            )
            ->where('outstanding_amount_minor', '>', 0)
            ->orderBy('due_date')
            ->get()
            ->mapWithKeys(fn (ContributionObligation $obligation): array => [
                $obligation->getKey() => '#'.$obligation->sequence_number
                    .' · '.$obligation->due_date->toDateString()
                    .' · '.self::money(
                        $obligation->outstanding_amount_minor,
                        $obligation->currency_code,
                    ),
            ])
            ->all();
    }

    /** @return array<string, string> */
    private static function permittedMethodOptions(
        RoleAuthorization $authorization,
    ): array {
        $methods = [];

        if ($authorization->can(
            FoundationPermission::ContributionPaymentsCollectCash->value,
        )) {
            $methods[ContributionPaymentMethod::Cash->value] = 'Cash';
        }

        if ($authorization->can(
            FoundationPermission::ContributionPaymentsRecordBank->value,
        )) {
            foreach ([
                ContributionPaymentMethod::BankTransfer,
                ContributionPaymentMethod::Cheque,
                ContributionPaymentMethod::CardManual,
            ] as $method) {
                $methods[$method->value] = Str::headline($method->value);
            }
        }

        if ($authorization->can(
            FoundationPermission::ContributionPaymentsRecordMobileMoney->value,
        )) {
            $methods[ContributionPaymentMethod::MobileMoneyManual->value]
                = 'Mobile Money (manual)';
        }

        return $methods;
    }

    /** @return array<string, string> */
    private static function allocationStrategyOptions(
        RoleAuthorization $authorization,
    ): array {
        if (! $authorization->can(
            FoundationPermission::ContributionPaymentsAllocate->value,
        )) {
            return [
                PaymentAllocationStrategy::LeaveUnallocated->value => 'Leave unallocated',
            ];
        }

        return collect(PaymentAllocationStrategy::cases())
            ->mapWithKeys(fn (PaymentAllocationStrategy $strategy): array => [
                $strategy->value => Str::headline($strategy->value),
            ])
            ->all();
    }

    /** @return array<int, string> */
    private static function sourceWalletOptions(
        mixed $methodValue,
        TenantContext $context,
    ): array {
        try {
            $method = ContributionPaymentMethod::from((string) $methodValue);
        } catch (\ValueError) {
            return [];
        }

        $types = match ($method) {
            ContributionPaymentMethod::Cash => [
                WalletType::OfficeCash,
                WalletType::CollectorCash,
            ],
            ContributionPaymentMethod::BankTransfer,
            ContributionPaymentMethod::Cheque,
            ContributionPaymentMethod::CardManual => [WalletType::Bank],
            ContributionPaymentMethod::MobileMoneyManual => [
                WalletType::MobileMoney,
            ],
            ContributionPaymentMethod::Adjustment => [],
        };

        return Wallet::query()
            ->whereBelongsTo($context->organisation())
            ->where('status', WalletStatus::Active)
            ->whereIn('wallet_type', $types)
            ->when(
                $context->branch(),
                fn (Builder $query): Builder => $query->where(
                    'branch_id',
                    $context->branch()?->getKey(),
                ),
            )
            ->when(
                $context->role()->name === SystemRole::Collector->value,
                fn (Builder $query): Builder => $query
                    ->where('wallet_type', WalletType::CollectorCash)
                    ->where('walletable_type', WalletOwnerType::CollectorUser->value)
                    ->where(
                        'walletable_id',
                        $context->roleAssignment()->user_id,
                    ),
            )
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (Wallet $wallet): array => [
                $wallet->getKey() => $wallet->code.' · '.$wallet->name,
            ])
            ->all();
    }

    private static function allocationPreview(
        Get $get,
        TenantContext $context,
        AllocateContributionPayment $allocator,
    ): string {
        if (
            ! is_numeric($get('amount_minor'))
            || (int) $get('amount_minor') <= 0
        ) {
            return 'Enter an amount to preview allocation.';
        }

        try {
            $member = self::resolveMember($get('member_id'), $context);
            $enrolment = self::resolveEnrolment(
                $get('enrolment_id'),
                $member,
                $context,
            );
            $strategy = PaymentAllocationStrategy::from(
                (string) $get('allocation_strategy'),
            );
            $result = $allocator->preview(new ContributionAllocationData(
                enrolment: $enrolment,
                plan: $enrolment?->contributionPlan,
                amountMinor: (int) $get('amount_minor'),
                allocationStrategy: $strategy,
                selectedAllocations: self::resolveSelectedAllocations(
                    $strategy === PaymentAllocationStrategy::SelectedObligations
                        ? ($get('allocations') ?? [])
                        : [],
                    $enrolment,
                    $context,
                ),
            ));
        } catch (InvalidContributionPayment|\ValueError) {
            return 'The current selections cannot be allocated.';
        }

        $summary = 'Allocated '.self::money(
            $result->allocatedAmountMinor,
            $context->organisation()->currency_code,
        ).'; unallocated '.self::money(
            $result->unallocatedAmountMinor,
            $context->organisation()->currency_code,
        ).'.';

        if ($result->allocations === []) {
            return $summary;
        }

        return $summary.' '.collect($result->allocations)
            ->map(fn (PaymentAllocationData $allocation): string => '#'
                .$allocation->obligation->sequence_number
                .': '.self::money(
                    $allocation->amountMinor,
                    $allocation->obligation->currency_code,
                ))
            ->implode('; ');
    }

    private static function resolveMember(
        mixed $memberId,
        TenantContext $context,
    ): Member {
        return self::memberQuery($context)
            ->whereKey(is_numeric($memberId) ? (int) $memberId : 0)
            ->first()
            ?? throw new InvalidContributionPayment(
                'The selected member is unavailable.',
            );
    }

    private static function resolveEnrolment(
        mixed $enrolmentId,
        Member $member,
        TenantContext $context,
    ): ?MemberPlanEnrolment {
        if ($enrolmentId === null || $enrolmentId === '') {
            return null;
        }

        return self::enrolmentQuery($context)
            ->where('member_id', $member->getKey())
            ->whereKey(is_numeric($enrolmentId) ? (int) $enrolmentId : 0)
            ->with(['contributionPlan.organisation'])
            ->first()
            ?? throw new InvalidContributionPayment(
                'The selected enrolment is unavailable.',
            );
    }

    private static function resolveSourceWallet(
        mixed $walletId,
        TenantContext $context,
    ): Wallet {
        return Wallet::query()
            ->whereBelongsTo($context->organisation())
            ->where('status', WalletStatus::Active)
            ->whereKey(is_numeric($walletId) ? (int) $walletId : 0)
            ->when(
                $context->branch(),
                fn (Builder $query): Builder => $query->where(
                    'branch_id',
                    $context->branch()?->getKey(),
                ),
            )
            ->first()
            ?? throw new InvalidContributionPayment(
                'The selected source wallet is unavailable.',
            );
    }

    /**
     * @return list<PaymentAllocationData>
     */
    private static function resolveSelectedAllocations(
        mixed $rows,
        ?MemberPlanEnrolment $enrolment,
        TenantContext $context,
    ): array {
        if (! is_array($rows) || $rows === []) {
            return [];
        }

        if (
            $enrolment === null
            || $enrolment->organisation_id !== $context->organisation()->getKey()
        ) {
            throw new InvalidContributionPayment(
                'An active enrolment is required for selected allocations.',
            );
        }

        $allocations = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                throw new InvalidContributionPayment(
                    'A selected obligation allocation is invalid.',
                );
            }

            $obligationId = $row['obligation_id'] ?? null;
            $obligation = ContributionObligation::query()
                ->whereBelongsTo($enrolment, 'enrolment')
                ->whereKey(is_numeric($obligationId) ? (int) $obligationId : 0)
                ->first()
                ?? throw new InvalidContributionPayment(
                    'A selected obligation is unavailable.',
                );
            $allocations[] = new PaymentAllocationData(
                $obligation,
                self::positiveInteger(
                    $row['amount_minor'] ?? null,
                    'A selected allocation amount is invalid.',
                ),
            );
        }

        return $allocations;
    }

    private static function positiveInteger(mixed $value, string $message): int
    {
        if (! is_numeric($value) || (int) $value <= 0) {
            throw new InvalidContributionPayment($message);
        }

        return (int) $value;
    }

    private static function requiredString(mixed $value, string $message): string
    {
        $string = self::optionalString($value);

        if ($string === null) {
            throw new InvalidContributionPayment($message);
        }

        return $string;
    }

    private static function optionalString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== ''
            ? trim($value)
            : null;
    }
}
