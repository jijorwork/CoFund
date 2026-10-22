<?php

namespace App\Filament\Resources\Members\RelationManagers;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Membership\Actions\AddMemberContact;
use App\Domain\Membership\Actions\DeactivateMemberContact;
use App\Domain\Membership\Actions\VerifyMemberContact;
use App\Domain\Membership\Enums\MemberContactStatus;
use App\Domain\Membership\Enums\MemberContactType;
use App\Domain\Membership\Exceptions\InvalidMembershipOperation;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Models\MemberContact;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Filament\Resources\Members\MemberResource;
use App\Filament\Support\MaskedPersonalData;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ContactsRelationManager extends RelationManager
{
    protected static string $relationship = 'contacts';

    protected static ?string $title = 'Contacts and beneficiaries';

    public function isReadOnly(): bool
    {
        return false;
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof Member
            && MemberResource::canView($ownerRecord)
            && app(RoleAuthorization::class)
                ->can(FoundationPermission::MembersManageContacts->value);
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Contact details')
                ->description('Personal details remain masked in the organisation panel.')
                ->schema([
                    TextEntry::make('full_name'),
                    TextEntry::make('contact_type')->formatStateUsing(static::formatType(...)),
                    TextEntry::make('relationship'),
                    TextEntry::make('phone')->formatStateUsing(MaskedPersonalData::phone(...)),
                    TextEntry::make('alternate_phone')
                        ->formatStateUsing(MaskedPersonalData::phone(...))
                        ->placeholder('Not provided'),
                    TextEntry::make('email')
                        ->formatStateUsing(MaskedPersonalData::email(...))
                        ->placeholder('Not provided'),
                    TextEntry::make('national_id_number')
                        ->label('National ID')
                        ->formatStateUsing(MaskedPersonalData::identifier(...))
                        ->placeholder('Not provided'),
                    TextEntry::make('percentage_entitlement')
                        ->label('Entitlement')
                        ->suffix('%')
                        ->placeholder('Not applicable'),
                    IconEntry::make('is_primary')->boolean(),
                    IconEntry::make('is_emergency_contact')->boolean(),
                    TextEntry::make('status')->badge(),
                    TextEntry::make('verified_at')->dateTime()->placeholder('Not verified'),
                    TextEntry::make('verifiedBy.name')->label('Verified by')->placeholder('Not verified'),
                    TextEntry::make('public_id')->label('Contact UUID')->copyable(),
                ])
                ->columns(['md' => 2]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('verifiedBy'))
            ->recordTitleAttribute('full_name')
            ->columns([
                TextColumn::make('full_name')->searchable()->sortable()->weight('medium'),
                TextColumn::make('contact_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(static::formatType(...)),
                TextColumn::make('relationship')->visibleFrom('md'),
                TextColumn::make('phone')
                    ->formatStateUsing(MaskedPersonalData::phone(...))
                    ->visibleFrom('lg'),
                TextColumn::make('percentage_entitlement')
                    ->label('Entitlement')
                    ->suffix('%')
                    ->placeholder('—')
                    ->visibleFrom('md'),
                IconColumn::make('is_primary')->boolean()->label('Primary')->visibleFrom('xl'),
                TextColumn::make('status')->badge(),
                TextColumn::make('verified_at')
                    ->label('Verified')
                    ->date()
                    ->placeholder('No')
                    ->visibleFrom('lg'),
            ])
            ->filters([
                SelectFilter::make('contact_type')->options(
                    collect(MemberContactType::cases())->mapWithKeys(
                        fn (MemberContactType $type): array => [
                            $type->value => Str::headline($type->value),
                        ],
                    )->all(),
                ),
                SelectFilter::make('status')->options(
                    collect(MemberContactStatus::cases())->mapWithKeys(
                        fn (MemberContactStatus $status): array => [
                            $status->value => Str::headline($status->value),
                        ],
                    )->all(),
                ),
            ])
            ->headerActions([
                Action::make('addContact')
                    ->label('Add contact')
                    ->icon('heroicon-o-user-plus')
                    ->visible(fn (RoleAuthorization $authorization): bool => $authorization
                        ->can(FoundationPermission::MembersManageContacts->value))
                    ->schema([
                        Select::make('contact_type')
                            ->options(collect(MemberContactType::cases())->mapWithKeys(
                                fn (MemberContactType $type): array => [
                                    $type->value => Str::headline($type->value),
                                ],
                            )->all())
                            ->required(),
                        TextInput::make('full_name')->required()->maxLength(255),
                        TextInput::make('relationship')->required()->maxLength(100),
                        TextInput::make('phone')->tel()->required()->maxLength(30),
                        TextInput::make('alternate_phone')->tel()->maxLength(30),
                        TextInput::make('email')->email()->maxLength(255),
                        Textarea::make('address')->maxLength(2000),
                        TextInput::make('national_id_type')->maxLength(100),
                        TextInput::make('national_id_number')
                            ->password()
                            ->revealable(false)
                            ->maxLength(255)
                            ->helperText('Write-only in this panel after submission.'),
                        TextInput::make('percentage_entitlement')
                            ->label('Beneficiary entitlement')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->rule('decimal:0,2')
                            ->requiredIf('contact_type', MemberContactType::Beneficiary->value)
                            ->prohibitedUnless('contact_type', MemberContactType::Beneficiary->value)
                            ->suffix('%'),
                        Toggle::make('is_primary'),
                        Toggle::make('is_emergency_contact'),
                    ])
                    ->modalHeading('Add contact or beneficiary')
                    ->modalDescription('Contact details will be masked after submission.')
                    ->action(function (
                        array $data,
                        Action $action,
                        AddMemberContact $domainAction,
                        RoleAuthorization $authorization,
                    ): void {
                        $authorization->authorize(FoundationPermission::MembersManageContacts->value);

                        try {
                            $domainAction->handle(
                                $this->member(),
                                Arr::only($data, [
                                    'contact_type',
                                    'full_name',
                                    'relationship',
                                    'phone',
                                    'alternate_phone',
                                    'email',
                                    'address',
                                    'national_id_type',
                                    'national_id_number',
                                    'percentage_entitlement',
                                    'is_primary',
                                    'is_emergency_contact',
                                ]),
                            );
                        } catch (InvalidMembershipOperation $exception) {
                            throw self::validationException($exception, $action);
                        }

                        Notification::make()->title('Contact added')->success()->send();
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('verify')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (MemberContact $record, RoleAuthorization $authorization): bool => $record->verified_at === null
                        && $record->status === MemberContactStatus::Active
                        && $authorization->can(FoundationPermission::MembersVerifyContacts->value))
                    ->action(function (
                        MemberContact $record,
                        VerifyMemberContact $domainAction,
                        RoleAuthorization $authorization,
                        TenantContext $tenantContext,
                    ): void {
                        $authorization->authorize(FoundationPermission::MembersVerifyContacts->value);

                        try {
                            $domainAction->handle(
                                $this->member(),
                                $record,
                                $tenantContext->roleAssignment()->user,
                            );
                        } catch (InvalidMembershipOperation $exception) {
                            throw self::validationException($exception);
                        }

                        Notification::make()->title('Contact verified')->success()->send();
                    }),
                Action::make('deactivate')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (MemberContact $record, RoleAuthorization $authorization): bool => $record->status === MemberContactStatus::Active
                        && $authorization->can(FoundationPermission::MembersManageContacts->value))
                    ->action(function (
                        MemberContact $record,
                        DeactivateMemberContact $domainAction,
                        RoleAuthorization $authorization,
                    ): void {
                        $authorization->authorize(FoundationPermission::MembersManageContacts->value);

                        try {
                            $domainAction->handle($this->member(), $record);
                        } catch (InvalidMembershipOperation $exception) {
                            throw self::validationException($exception);
                        }

                        Notification::make()->title('Contact deactivated')->success()->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([5, 10, 25]);
    }

    private function member(): Member
    {
        $owner = $this->getOwnerRecord();

        if (! $owner instanceof Member) {
            throw new \LogicException('The contact relation owner must be a member.');
        }

        return $owner;
    }

    private static function formatType(mixed $state): string
    {
        $type = $state instanceof MemberContactType
            ? $state
            : MemberContactType::from((string) $state);

        return Str::headline($type->value);
    }

    private static function validationException(
        InvalidMembershipOperation $exception,
        ?Action $action = null,
    ): ValidationException {
        $nestingIndex = $action?->getNestingIndex();
        $field = $nestingIndex === null
            ? 'percentage_entitlement'
            : "mountedActions.{$nestingIndex}.data.percentage_entitlement";

        return ValidationException::withMessages([
            $field => $exception->getMessage() !== ''
                ? $exception->getMessage()
                : 'The contact details are not valid for this member.',
        ]);
    }
}
