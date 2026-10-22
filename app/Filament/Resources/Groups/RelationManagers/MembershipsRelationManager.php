<?php

namespace App\Filament\Resources\Groups\RelationManagers;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Membership\Actions\AddMemberToGroup;
use App\Domain\Membership\Actions\RemoveMemberFromGroup;
use App\Domain\Membership\Enums\GroupMembershipStatus;
use App\Domain\Membership\Enums\MemberStatus;
use App\Domain\Membership\Exceptions\InvalidMembershipOperation;
use App\Domain\Membership\Models\Group;
use App\Domain\Membership\Models\GroupMembership;
use App\Domain\Membership\Models\Member;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Filament\Resources\Groups\GroupResource;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MembershipsRelationManager extends RelationManager
{
    protected static string $relationship = 'memberships';

    protected static ?string $title = 'Members';

    public function isReadOnly(): bool
    {
        return false;
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof Group
            && GroupResource::canView($ownerRecord)
            && app(RoleAuthorization::class)->can(FoundationPermission::GroupsView->value);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with([
                'member.branch',
                'assignedBy',
            ]))
            ->columns([
                TextColumn::make('member.membership_number')
                    ->label('Member number')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('member_name')
                    ->label('Name')
                    ->state(fn (GroupMembership $record): string => $record->member->fullName())
                    ->wrap(),
                TextColumn::make('member.branch.name')
                    ->label('Branch')
                    ->placeholder('Organisation-wide')
                    ->visibleFrom('md'),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): string => Str::headline(
                        (string) ($state instanceof GroupMembershipStatus ? $state->value : $state),
                    )),
                TextColumn::make('joined_at')->date()->visibleFrom('md'),
                TextColumn::make('left_at')->date()->placeholder('—')->visibleFrom('lg'),
                TextColumn::make('assignedBy.name')
                    ->label('Added by')
                    ->placeholder('System')
                    ->visibleFrom('xl'),
            ])
            ->filters([
                SelectFilter::make('status')->options(
                    collect(GroupMembershipStatus::cases())->mapWithKeys(
                        fn (GroupMembershipStatus $status): array => [
                            $status->value => Str::headline($status->value),
                        ],
                    )->all(),
                ),
            ])
            ->headerActions([
                Action::make('addMember')
                    ->label('Add member')
                    ->icon('heroicon-o-user-plus')
                    ->visible(fn (RoleAuthorization $authorization): bool => $authorization
                        ->can(FoundationPermission::GroupsManageMembers->value))
                    ->schema([
                        Select::make('member_uuid')
                            ->label('Member')
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search): array => $this
                                ->memberSearchResults($search))
                            ->getOptionLabelUsing(fn (mixed $value): ?string => is_string($value)
                                ? $this->memberOptionLabel($value)
                                : null)
                            ->required(),
                    ])
                    ->action(function (
                        array $data,
                        AddMemberToGroup $addMember,
                        RoleAuthorization $authorization,
                        TenantContext $tenantContext,
                    ): void {
                        $authorization->authorize(FoundationPermission::GroupsManageMembers->value);
                        $memberUuid = $data['member_uuid'] ?? null;

                        if (! is_string($memberUuid)) {
                            throw ValidationException::withMessages([
                                'member_uuid' => 'A valid member is required.',
                            ]);
                        }

                        $member = $this->memberQuery(excludeActive: true)
                            ->where('public_id', $memberUuid)
                            ->first();

                        if ($member === null) {
                            throw ValidationException::withMessages([
                                'member_uuid' => 'The selected member is unavailable in this context.',
                            ]);
                        }

                        try {
                            $addMember->handle(
                                $this->group(),
                                $member,
                                $tenantContext->roleAssignment()->user,
                            );
                        } catch (InvalidMembershipOperation $exception) {
                            throw ValidationException::withMessages([
                                'member_uuid' => $exception->getMessage(),
                            ]);
                        }

                        Notification::make()->title('Member added to group')->success()->send();
                    }),
            ])
            ->recordActions([
                Action::make('removeMember')
                    ->label('Remove')
                    ->icon('heroicon-o-user-minus')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (
                        GroupMembership $record,
                        RoleAuthorization $authorization,
                    ): bool => $record->status === GroupMembershipStatus::Active
                        && $authorization->can(FoundationPermission::GroupsManageMembers->value))
                    ->action(function (
                        GroupMembership $record,
                        RemoveMemberFromGroup $removeMember,
                        RoleAuthorization $authorization,
                    ): void {
                        $authorization->authorize(FoundationPermission::GroupsManageMembers->value);

                        try {
                            $removeMember->handle($this->group(), $record->member);
                        } catch (InvalidMembershipOperation $exception) {
                            throw ValidationException::withMessages([
                                'member_uuid' => $exception->getMessage(),
                            ]);
                        }

                        Notification::make()->title('Member removed from group')->success()->send();
                    }),
            ])
            ->defaultSort('joined_at', 'desc')
            ->paginated([10, 25, 50]);
    }

    private function group(): Group
    {
        $owner = $this->getOwnerRecord();

        if (! $owner instanceof Group) {
            throw new \LogicException('The membership relation owner must be a group.');
        }

        return $owner;
    }

    /** @return Builder<Member> */
    private function memberQuery(bool $excludeActive = false): Builder
    {
        $group = $this->group();
        $context = app(TenantContext::class);

        return Member::query()
            ->whereBelongsTo($context->organisation())
            ->where('status', MemberStatus::Active)
            ->when(
                $group->branch_id !== null,
                fn (Builder $query): Builder => $query->where('branch_id', $group->branch_id),
            )
            ->when(
                $context->branch() !== null,
                fn (Builder $query): Builder => $query->where(
                    'branch_id',
                    $context->branch()?->getKey(),
                ),
            )
            ->when(
                $excludeActive,
                fn (Builder $query): Builder => $query->whereDoesntHave(
                    'groupMemberships',
                    fn (Builder $memberships): Builder => $memberships
                        ->where('group_id', $group->getKey())
                        ->where('status', GroupMembershipStatus::Active),
                ),
            );
    }

    /** @return array<string, string> */
    private function memberSearchResults(string $search): array
    {
        return $this->memberQuery(excludeActive: true)
            ->where(function (Builder $query) use ($search): void {
                $query
                    ->where('membership_number', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('middle_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%");
            })
            ->orderBy('membership_number')
            ->limit(50)
            ->get()
            ->mapWithKeys(fn (Member $member): array => [
                $member->public_id => self::memberLabel($member),
            ])
            ->all();
    }

    private function memberOptionLabel(string $uuid): ?string
    {
        $member = $this->memberQuery()
            ->where('public_id', $uuid)
            ->first();

        return $member === null ? null : self::memberLabel($member);
    }

    private static function memberLabel(Member $member): string
    {
        return "{$member->membership_number} — {$member->fullName()}";
    }
}
