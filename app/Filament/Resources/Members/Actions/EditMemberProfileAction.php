<?php

namespace App\Filament\Resources\Members\Actions;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Membership\Actions\UpdateMemberProfile;
use App\Domain\Membership\Enums\MemberGender;
use App\Domain\Membership\Exceptions\InvalidMembershipOperation;
use App\Domain\Membership\Models\Member;
use App\Filament\Resources\Members\MemberResource;
use Filament\Actions\Action;
use Filament\Actions\ActionName;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

#[ActionName('editProfile')]
final class EditMemberProfileAction
{
    public static function make(): Action
    {
        return Action::make('editProfile')
            ->label('Edit profile')
            ->icon('heroicon-o-pencil-square')
            ->visible(fn (
                Member $record,
                RoleAuthorization $authorization,
            ): bool => MemberResource::canView($record)
                && $authorization->can(FoundationPermission::MembersUpdate->value))
            ->fillForm(fn (Member $record): array => [
                ...$record->only([
                    'first_name',
                    'middle_name',
                    'last_name',
                    'preferred_name',
                    'phone',
                    'alternate_phone',
                    'email',
                    'national_id_type',
                    'occupation',
                    'residential_address',
                    'postal_address',
                    'notes',
                ]),
                'gender' => $record->gender?->value,
                'date_of_birth' => $record->date_of_birth?->toDateString(),
            ])
            ->schema(self::schema())
            ->modalHeading('Edit member profile')
            ->modalDescription(
                'Membership number, branch, account link, status, and lifecycle dates are managed separately.',
            )
            ->modalSubmitActionLabel('Save profile')
            ->slideOver()
            ->action(function (
                Member $record,
                array $data,
                UpdateMemberProfile $updateMemberProfile,
                RoleAuthorization $authorization,
            ): void {
                $authorization->authorize(FoundationPermission::MembersUpdate->value);

                try {
                    $updateMemberProfile->handle(
                        $record,
                        self::profile($data),
                        $record->branch,
                    );
                } catch (InvalidMembershipOperation $exception) {
                    throw ValidationException::withMessages([
                        'first_name' => $exception->getMessage(),
                    ]);
                }

                Notification::make()
                    ->title('Member profile updated')
                    ->success()
                    ->send();
            });
    }

    /** @return list<Section> */
    private static function schema(): array
    {
        return [
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
                        )->all())
                        ->enum(MemberGender::class),
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
            Section::make('Additional details')
                ->schema([
                    TextInput::make('national_id_type')
                        ->label('National ID type')
                        ->maxLength(100),
                    TextInput::make('national_id_number')
                        ->label('Replace national ID number')
                        ->password()
                        ->revealable(false)
                        ->maxLength(255)
                        ->helperText('Leave blank to keep the existing encrypted value.'),
                    Textarea::make('notes')
                        ->maxLength(5000)
                        ->columnSpanFull(),
                ])
                ->columns(['md' => 2]),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function profile(array $data): array
    {
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
            'national_id_type',
            'national_id_number',
            'occupation',
            'residential_address',
            'postal_address',
            'notes',
        ]);

        if (blank($profile['national_id_number'] ?? null)) {
            unset($profile['national_id_number']);
        }

        return $profile;
    }
}
