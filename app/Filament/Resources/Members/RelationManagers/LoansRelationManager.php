<?php

namespace App\Filament\Resources\Members\RelationManagers;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Loans\Models\Loan;
use App\Domain\Membership\Models\Member;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Filament\Resources\Members\MemberResource;
use App\Filament\Support\FormatsMoney;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class LoansRelationManager extends RelationManager
{
    protected static string $relationship = 'loans';

    protected static ?string $title = 'Loans';

    public function isReadOnly(): bool
    {
        return true;
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof Member
            && MemberResource::canView($ownerRecord)
            && app(RoleAuthorization::class)->can(FoundationPermission::LoansView->value);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query): Builder {
                $context = app(TenantContext::class);

                return $query
                    ->where('organisation_id', $context->organisation()->getKey())
                    ->when($context->branch(), fn (Builder $query, $branch): Builder => $query
                        ->where('branch_id', $branch->getKey()))
                    ->with('product');
            })
            ->columns([
                TextColumn::make('loan_number')->label('Loan number')->copyable(),
                TextColumn::make('product.name')->label('Product')->wrap()->visibleFrom('md'),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(self::formatStatus(...)),
                TextColumn::make('performance_status')
                    ->label('Performance')
                    ->badge()
                    ->formatStateUsing(self::formatStatus(...))
                    ->visibleFrom('lg'),
                TextColumn::make('outstanding')
                    ->state(fn (Loan $record): string => FormatsMoney::format(
                        $record->outstanding_principal_minor
                            + $record->outstanding_interest_minor
                            + $record->outstanding_fees_minor
                            + $record->outstanding_penalties_minor,
                        $record->currency_code,
                    )),
                TextColumn::make('arrears_minor')
                    ->label('Arrears')
                    ->formatStateUsing(fn (mixed $state, Loan $record): string => FormatsMoney::format(
                        (int) $state,
                        $record->currency_code,
                    ))
                    ->visibleFrom('md'),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50]);
    }

    private static function formatStatus(mixed $state): string
    {
        return Str::headline((string) ($state instanceof \BackedEnum ? $state->value : $state));
    }
}
