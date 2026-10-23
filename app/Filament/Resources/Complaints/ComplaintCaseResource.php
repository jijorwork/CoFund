<?php

namespace App\Filament\Resources\Complaints;

use App\Domain\Complaints\Actions\ManageComplaintCase;
use App\Domain\Complaints\Enums\ComplaintStatus;
use App\Domain\Complaints\Exceptions\InvalidComplaintOperation;
use App\Domain\Complaints\Models\ComplaintCase;
use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Models\OrganisationUserRole;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Membership\Models\Member;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Filament\Resources\Complaints\Pages\ListComplaintCases;
use App\Filament\Resources\Complaints\Pages\ViewComplaintCase;
use App\Filament\Resources\TenantResource;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ComplaintCaseResource extends TenantResource
{
    protected static ?string $model = ComplaintCase::class;

    protected static string $viewPermission = FoundationPermission::ComplaintsView->value;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static string|\UnitEnum|null $navigationGroup = 'Members';

    protected static ?int $navigationSort = 50;

    protected static ?string $recordTitleAttribute = 'subject';

    protected static bool $isGloballySearchable = true;

    /** @return list<string> */
    public static function getGloballySearchableAttributes(): array
    {
        return ['subject', 'public_id', 'member.membership_number'];
    }

    /** @return Builder<ComplaintCase> */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<ComplaintCase> $query */
        $query = parent::getEloquentQuery();

        return $query->with(['branch', 'member', 'reportedBy', 'assignedTo']);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Case')
                ->schema([
                    TextEntry::make('subject')->columnSpanFull(),
                    TextEntry::make('category'),
                    TextEntry::make('status')->badge()->formatStateUsing(self::formatStatus(...)),
                    TextEntry::make('member.membership_number')->label('Member')->placeholder('No linked member'),
                    TextEntry::make('branch.name')->placeholder('Organisation-wide'),
                    TextEntry::make('reportedBy.name')->label('Reported by'),
                    TextEntry::make('assignedTo.name')->label('Assigned to')->placeholder('Unassigned'),
                    TextEntry::make('created_at')->dateTime(),
                    TextEntry::make('public_id')->label('Case UUID')->copyable(),
                ])
                ->columns(['md' => 2]),
            Section::make('Resolution')
                ->schema([
                    TextEntry::make('resolution_summary')->placeholder('Not resolved')->columnSpanFull(),
                    TextEntry::make('resolved_at')->dateTime()->placeholder('Not resolved'),
                    TextEntry::make('closed_at')->dateTime()->placeholder('Not closed'),
                ])
                ->columns(['md' => 2]),
            Section::make('Correspondence')
                ->schema([
                    RepeatableEntry::make('messages')
                        ->hiddenLabel()
                        ->schema([
                            TextEntry::make('author.name')->label('Author'),
                            TextEntry::make('visibility')->badge(),
                            TextEntry::make('created_at')->dateTime(),
                            TextEntry::make('body')->columnSpanFull(),
                        ])
                        ->columns(['md' => 3]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('subject')->searchable()->sortable()->limit(60),
                TextColumn::make('category')->searchable()->visibleFrom('md'),
                TextColumn::make('member.membership_number')->label('Member')->searchable(),
                TextColumn::make('assignedTo.name')->label('Assigned to')->placeholder('Unassigned')->visibleFrom('lg'),
                TextColumn::make('branch.name')->placeholder('Organisation-wide')->visibleFrom('xl'),
                TextColumn::make('status')->badge()->formatStateUsing(self::formatStatus(...)),
                TextColumn::make('created_at')->dateTime()->sortable()->visibleFrom('md'),
            ])
            ->filters([
                SelectFilter::make('status')->options(self::statusOptions()),
            ])
            ->headerActions([self::openAction()])
            ->recordActions([ViewAction::make(), self::respondAction(), self::assignAction(), self::resolveAction(), self::closeAction(), self::reopenAction()])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListComplaintCases::route('/'),
            'view' => ViewComplaintCase::route('/{record}'),
        ];
    }

    public static function openAction(): Action
    {
        return Action::make('openCase')
            ->label('Open case')
            ->icon('heroicon-o-plus')
            ->visible(fn (RoleAuthorization $authorization): bool => $authorization->can(FoundationPermission::ComplaintsCreate->value))
            ->schema([
                Select::make('member_id')
                    ->label('Member')
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search): array => self::memberOptions($search))
                    ->getOptionLabelUsing(fn (mixed $value): ?string => self::memberLabel($value)),
                TextInput::make('subject')->required()->minLength(3)->maxLength(200),
                TextInput::make('category')->required()->maxLength(80),
                Textarea::make('body')->label('Initial message')->required()->minLength(3)->maxLength(20000),
            ])
            ->action(function (array $data, ManageComplaintCase $cases, RoleAuthorization $authorization): void {
                $authorization->authorize(FoundationPermission::ComplaintsCreate->value);
                $context = app(TenantContext::class);
                $member = isset($data['member_id'])
                    ? Member::query()
                        ->where('organisation_id', $context->organisation()->getKey())
                        ->when($context->branch(), fn (Builder $query) => $query->where('branch_id', $context->branch()->getKey()))
                        ->whereKey($data['member_id'])->firstOrFail()
                    : null;
                $actor = auth()->user();
                abort_unless($actor instanceof User, 401);
                try {
                    $cases->open($actor, $member, $data['subject'], $data['category'], $data['body']);
                } catch (InvalidComplaintOperation $exception) {
                    throw ValidationException::withMessages(['member_id' => $exception->getMessage()]);
                }
                Notification::make()->title('Case opened')->success()->send();
            });
    }

    public static function respondAction(): Action
    {
        return Action::make('respond')
            ->label('Add message')
            ->icon('heroicon-o-chat-bubble-left')
            ->visible(fn (ComplaintCase $record, RoleAuthorization $authorization): bool => static::canView($record)
                && $record->status !== ComplaintStatus::Closed
                && $authorization->can(FoundationPermission::ComplaintsRespond->value))
            ->schema([
                Select::make('visibility')->options(['internal' => 'Internal', 'complainant' => 'Complainant'])->required(),
                Textarea::make('body')->required()->maxLength(20000),
            ])
            ->action(function (ComplaintCase $record, array $data, ManageComplaintCase $cases, RoleAuthorization $authorization): void {
                $authorization->authorize(FoundationPermission::ComplaintsRespond->value);
                self::runCaseAction(fn () => $cases->respond($record, self::actor(), $data['body'], $data['visibility']), 'Message added');
            });
    }

    public static function assignAction(): Action
    {
        return Action::make('assign')
            ->label('Assign')
            ->icon('heroicon-o-user-plus')
            ->visible(fn (ComplaintCase $record, RoleAuthorization $authorization): bool => static::canView($record)
                && in_array($record->status, [ComplaintStatus::Open, ComplaintStatus::InReview], true)
                && $authorization->can(FoundationPermission::ComplaintsManage->value))
            ->schema([
                Select::make('assignee_id')
                    ->label('Assignee')
                    ->options(fn (ComplaintCase $record): array => self::assigneeOptions($record))
                    ->searchable()
                    ->required(),
            ])
            ->action(function (ComplaintCase $record, array $data, ManageComplaintCase $cases, RoleAuthorization $authorization): void {
                $authorization->authorize(FoundationPermission::ComplaintsManage->value);
                $assignee = User::query()->whereKey($data['assignee_id'])->firstOrFail();
                self::runCaseAction(fn () => $cases->assign($record, self::actor(), $assignee), 'Case assigned');
            });
    }

    public static function resolveAction(): Action
    {
        return Action::make('resolve')
            ->label('Resolve')
            ->icon('heroicon-o-check-circle')
            ->visible(fn (ComplaintCase $record, RoleAuthorization $authorization): bool => static::canView($record)
                && in_array($record->status, [ComplaintStatus::Open, ComplaintStatus::InReview], true)
                && $authorization->can(FoundationPermission::ComplaintsResolve->value))
            ->schema([Textarea::make('resolution_summary')->required()->minLength(3)->maxLength(20000)])
            ->action(function (ComplaintCase $record, array $data, ManageComplaintCase $cases, RoleAuthorization $authorization): void {
                $authorization->authorize(FoundationPermission::ComplaintsResolve->value);
                self::runCaseAction(fn () => $cases->resolve($record, self::actor(), $data['resolution_summary']), 'Case resolved');
            });
    }

    public static function closeAction(): Action
    {
        return Action::make('close')
            ->label('Close')
            ->icon('heroicon-o-lock-closed')
            ->requiresConfirmation()
            ->visible(fn (ComplaintCase $record, RoleAuthorization $authorization): bool => static::canView($record)
                && $record->status === ComplaintStatus::Resolved
                && $authorization->can(FoundationPermission::ComplaintsResolve->value))
            ->action(function (ComplaintCase $record, ManageComplaintCase $cases, RoleAuthorization $authorization): void {
                $authorization->authorize(FoundationPermission::ComplaintsResolve->value);
                self::runCaseAction(fn () => $cases->close($record, self::actor()), 'Case closed');
            });
    }

    public static function reopenAction(): Action
    {
        return Action::make('reopen')
            ->label('Reopen')
            ->icon('heroicon-o-arrow-path')
            ->requiresConfirmation()
            ->visible(fn (ComplaintCase $record, RoleAuthorization $authorization): bool => static::canView($record)
                && in_array($record->status, [ComplaintStatus::Resolved, ComplaintStatus::Closed], true)
                && $authorization->can(FoundationPermission::ComplaintsManage->value))
            ->action(function (ComplaintCase $record, ManageComplaintCase $cases, RoleAuthorization $authorization): void {
                $authorization->authorize(FoundationPermission::ComplaintsManage->value);
                self::runCaseAction(fn () => $cases->reopen($record, self::actor()), 'Case reopened');
            });
    }

    /** @return array<int, string> */
    private static function assigneeOptions(ComplaintCase $record): array
    {
        return OrganisationUserRole::query()
            ->currentlyValid()
            ->with('user')
            ->where('organisation_id', $record->organisation_id)
            ->where(fn (Builder $query): Builder => $query->whereNull('branch_id')->orWhere('branch_id', $record->branch_id))
            ->get()
            ->filter(fn (OrganisationUserRole $assignment): bool => $assignment->user !== null)
            ->mapWithKeys(fn (OrganisationUserRole $assignment): array => [$assignment->user_id => $assignment->user->name])
            ->all();
    }

    private static function actor(): User
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User, 401);

        return $actor;
    }

    private static function runCaseAction(\Closure $action, string $successTitle): void
    {
        try {
            $action();
        } catch (InvalidComplaintOperation $exception) {
            throw ValidationException::withMessages(['case' => $exception->getMessage()]);
        }
        Notification::make()->title($successTitle)->success()->send();
    }

    /** @return array<int, string> */
    private static function memberOptions(string $search): array
    {
        $context = app(TenantContext::class);

        return Member::query()
            ->where('organisation_id', $context->organisation()->getKey())
            ->when($context->branch(), fn (Builder $query) => $query->where('branch_id', $context->branch()->getKey()))
            ->where('membership_number', 'like', '%'.$search.'%')
            ->limit(25)
            ->pluck('membership_number', 'id')
            ->all();
    }

    private static function memberLabel(mixed $value): ?string
    {
        $context = app(TenantContext::class);

        return Member::query()
            ->where('organisation_id', $context->organisation()->getKey())
            ->when($context->branch(), fn (Builder $query) => $query->where('branch_id', $context->branch()->getKey()))
            ->whereKey($value)
            ->value('membership_number');
    }

    /** @return array<string, string> */
    private static function statusOptions(): array
    {
        return collect(ComplaintStatus::cases())
            ->mapWithKeys(fn (ComplaintStatus $status): array => [$status->value => Str::headline($status->value)])
            ->all();
    }

    private static function formatStatus(mixed $state): string
    {
        return Str::headline((string) ($state instanceof BackedEnum ? $state->value : $state));
    }
}
