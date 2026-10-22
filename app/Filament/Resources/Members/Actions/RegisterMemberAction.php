<?php

namespace App\Filament\Resources\Members\Actions;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Membership\Actions\CreateMember;
use App\Domain\Membership\Enums\MemberGender;
use App\Domain\Membership\Exceptions\InvalidMembershipOperation;
use App\Domain\Organisations\Models\Branch;
use App\Domain\Organisations\Tenancy\TenantContext;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class RegisterMemberAction
{
    public static function make(): Action
    {
        return Action::make('registerMember')
            ->label('Register member')
            ->icon('heroicon-o-user-plus')
            ->visible(fn (RoleAuthorization $authorization): bool => $authorization
                ->can(FoundationPermission::MembersCreate->value))
            ->modalHeading('Register member')
            ->modalDescription(
                'Personal data entered here is stored securely and remains masked on organisation administration screens.',
            )
            ->modalSubmitActionLabel('Register member')
            ->slideOver()
            ->schema([
                Section::make('Identity')
                    ->schema([
                        TextInput::make('first_name')->required()->maxLength(255),
                        TextInput::make('middle_name')->maxLength(255),
                        TextInput::make('last_name')->required()->maxLength(255),
                        TextInput::make('preferred_name')->maxLength(255),
                        Select::make('gender')
                            ->options(collect(MemberGender::cases())->mapWithKeys(
                                fn (MemberGender $gender): array => [
                                    $gender->value => Str::headline($gender->value),
                                ],
                            )->all()),
                        DatePicker::make('date_of_birth')
                            ->maxDate(now())
                            ->native(false),
                    ])
                    ->columns(['md' => 2]),
                Section::make('Contact')
                    ->schema([
                        TextInput::make('phone')
                            ->tel()
                            ->required()
                            ->maxLength(30),
                        TextInput::make('alternate_phone')
                            ->tel()
                            ->maxLength(30),
                        TextInput::make('email')
                            ->email()
                            ->maxLength(255),
                        TextInput::make('occupation')->maxLength(255),
                        Textarea::make('residential_address')->maxLength(2000),
                        Textarea::make('postal_address')->maxLength(2000),
                    ])
                    ->columns(['md' => 2]),
                Section::make('Registration')
                    ->schema([
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
                            ->dehydrated(),
                        DatePicker::make('joined_at')
                            ->label('Join date')
                            ->default(now())
                            ->maxDate(now())
                            ->required(),
                        TextInput::make('national_id_type')
                            ->label('National ID type')
                            ->maxLength(100),
                        TextInput::make('national_id_number')
                            ->label('National ID number')
                            ->password()
                            ->revealable(false)
                            ->maxLength(255)
                            ->helperText('Write-only in this panel after registration.'),
                        Textarea::make('notes')
                            ->maxLength(5000)
                            ->columnSpanFull(),
                    ])
                    ->columns(['md' => 2]),
            ])
            ->action(function (
                array $data,
                CreateMember $createMember,
                RoleAuthorization $authorization,
                TenantContext $tenantContext,
            ): void {
                $authorization->authorize(FoundationPermission::MembersCreate->value);
                $branch = static::resolveBranch($data['branch_id'] ?? null, $tenantContext);
                $profile = Arr::only($data, [
                    'first_name',
                    'middle_name',
                    'last_name',
                    'preferred_name',
                    'gender',
                    'date_of_birth',
                    'phone',
                    'alternate_phone',
                    'email',
                    'occupation',
                    'residential_address',
                    'postal_address',
                    'joined_at',
                    'national_id_type',
                    'national_id_number',
                    'notes',
                ]);

                try {
                    $createMember->handle(
                        actor: $tenantContext->roleAssignment()->user,
                        profile: $profile,
                        branch: $branch,
                    );
                } catch (InvalidMembershipOperation $exception) {
                    throw ValidationException::withMessages([
                        'branch_id' => $exception->getMessage(),
                    ]);
                }

                Notification::make()
                    ->title('Member registered')
                    ->success()
                    ->send();
            });
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
                throw ValidationException::withMessages([
                    'branch_id' => 'The active branch is required.',
                ]);
            }

            return null;
        }

        if (! is_int($branchId) && ! (is_string($branchId) && ctype_digit($branchId))) {
            throw ValidationException::withMessages([
                'branch_id' => 'A valid branch is required.',
            ]);
        }

        $branch = Branch::query()
            ->active()
            ->whereBelongsTo($context->organisation())
            ->whereKey($branchId)
            ->first();

        if (
            $branch === null
            || ($context->branch() !== null && ! $branch->is($context->branch()))
        ) {
            throw ValidationException::withMessages([
                'branch_id' => 'The selected branch is unavailable in the active context.',
            ]);
        }

        return $branch;
    }
}
