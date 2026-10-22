<?php

namespace App\Filament\Resources\Members\RelationManagers;

use App\Domain\Contributions\Models\MemberPlanEnrolment;
use App\Domain\Contributions\Services\CalculateContributionPosition;
use App\Domain\Contributions\Services\ContributionScope;
use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Membership\Models\Member;
use App\Filament\Resources\Members\MemberResource;
use App\Filament\Support\FormatsMoney;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ContributionEnrolmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'contributionEnrolments';

    protected static ?string $title = 'Contribution positions';

    public function isReadOnly(): bool
    {
        return true;
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof Member
            && MemberResource::canView($ownerRecord)
            && app(RoleAuthorization::class)
                ->can(FoundationPermission::ContributionEnrolmentsView->value);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => app(ContributionScope::class)
                ->applyEnrolmentAccess($query
                    ->where('organisation_id', $this->getOwnerRecord()->organisation_id)
                    ->with(['contributionPlan.organisation', 'obligations'])))
            ->columns([
                TextColumn::make('contributionPlan.name')
                    ->label('Plan')
                    ->wrap(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): string => Str::headline(
                        (string) ($state instanceof \BackedEnum ? $state->value : $state),
                    )),
                TextColumn::make('position')
                    ->state(self::positionSummary(...))
                    ->wrap(),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50]);
    }

    private static function positionSummary(MemberPlanEnrolment $record): string
    {
        $position = app(CalculateContributionPosition::class)->calculate($record);
        $currency = $record->currency_code;

        return sprintf(
            '%s · Expected %s · Satisfied %s · Outstanding %s',
            Str::headline($position->status->value),
            FormatsMoney::format($position->expectedToDateMinor, $currency),
            FormatsMoney::format($position->satisfiedToDateMinor, $currency),
            FormatsMoney::format($position->outstandingToDateMinor, $currency),
        );
    }
}
