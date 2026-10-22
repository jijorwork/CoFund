<?php

namespace App\Filament\Resources\Organisations;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Organisations\Actions\UpdateOrganisationProfile;
use App\Domain\Organisations\Enums\OrganisationStatus;
use App\Domain\Organisations\Exceptions\InvalidOrganisationAccess;
use App\Domain\Organisations\Models\Organisation;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Filament\Resources\Organisations\Pages\ListOrganisations;
use App\Filament\Resources\Organisations\Pages\ViewOrganisation;
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
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrganisationResource extends TenantResource
{
    protected static ?string $model = Organisation::class;

    protected static string $viewPermission = FoundationPermission::OrganisationsView->value;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice;

    protected static string|\UnitEnum|null $navigationGroup = 'Organisation';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Organisation profile';

    protected static ?string $slug = 'organisation';

    protected static ?string $recordTitleAttribute = 'name';

    protected static bool $isGloballySearchable = true;

    /** @return Builder<Organisation> */
    public static function getEloquentQuery(): Builder
    {
        return Organisation::query()
            ->whereKey(app(TenantContext::class)->organisation()->getKey())
            ->withCount(['branches', 'members', 'organisationAccesses', 'userRoleAssignments']);
    }

    public static function canView(Model $record): bool
    {
        return static::canViewAny()
            && $record->is(app(TenantContext::class)->organisation());
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Organisation details')
                ->schema([
                    TextEntry::make('name'),
                    TextEntry::make('code'),
                    TextEntry::make('organisation_type')
                        ->label('Type')
                        ->formatStateUsing(static::formatState(...)),
                    TextEntry::make('status')
                        ->badge()
                        ->formatStateUsing(static::formatState(...))
                        ->color(fn (mixed $state): string => match (static::status($state)) {
                            OrganisationStatus::Active => 'success',
                            OrganisationStatus::Suspended => 'warning',
                            OrganisationStatus::Pending => 'info',
                            OrganisationStatus::Inactive => 'gray',
                        }),
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
                TextColumn::make('name')->searchable()->weight('medium'),
                TextColumn::make('code'),
                TextColumn::make('status')->badge()->formatStateUsing(static::formatState(...)),
                TextColumn::make('branches_count')->label('Branches')->numeric()->visibleFrom('md'),
                TextColumn::make('members_count')->label('Members')->numeric()->visibleFrom('lg'),
                TextColumn::make('organisation_accesses_count')->label('People')->numeric()->visibleFrom('xl'),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('editProfile')
                    ->label('Edit profile')
                    ->icon('heroicon-o-pencil-square')
                    ->visible(fn (
                        Organisation $record,
                        RoleAuthorization $authorization,
                        TenantContext $tenantContext,
                    ): bool => static::canView($record)
                        && $tenantContext->branch() === null
                        && $authorization->can(FoundationPermission::OrganisationsUpdate->value))
                    ->fillForm(fn (Organisation $record): array => $record->only([
                        'name',
                        'code',
                        'phone',
                        'email',
                        'address',
                    ]))
                    ->schema(self::profileSchema())
                    ->modalHeading('Edit organisation profile')
                    ->modalDescription(
                        'Status, organisation type, currency, time zone, and billing are managed separately.',
                    )
                    ->action(function (
                        Organisation $record,
                        array $data,
                        UpdateOrganisationProfile $updateOrganisationProfile,
                    ): void {
                        try {
                            $updateOrganisationProfile->handle(
                                $record,
                                self::profileAttributes($data),
                            );
                        } catch (InvalidOrganisationAccess $exception) {
                            throw ValidationException::withMessages([
                                'code' => $exception->getMessage(),
                            ]);
                        }

                        Notification::make()
                            ->title('Organisation profile updated')
                            ->success()
                            ->send();
                    }),
            ])
            ->paginated(false);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrganisations::route('/'),
            'view' => ViewOrganisation::route('/{record}'),
        ];
    }

    protected static function formatState(mixed $state): string
    {
        return Str::headline((string) ($state instanceof BackedEnum ? $state->value : $state));
    }

    protected static function status(mixed $state): OrganisationStatus
    {
        return $state instanceof OrganisationStatus
            ? $state
            : OrganisationStatus::from((string) $state);
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
    private static function profileAttributes(array $data): array
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
                'name' => 'An organisation name and code are required.',
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
    private static function profileSchema(): array
    {
        return [
            TextInput::make('name')
                ->required()
                ->maxLength(255),
            TextInput::make('code')
                ->required()
                ->maxLength(255)
                ->unique(
                    table: Organisation::class,
                    column: 'code',
                    ignoreRecord: true,
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
