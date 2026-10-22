<?php

namespace App\Filament\Resources\Accounting;

use App\Domain\Accounting\Models\FinancialTransaction;
use App\Domain\Identity\Enums\FoundationPermission;
use App\Filament\Resources\Accounting\Pages\ListFinancialTransactions;
use App\Filament\Resources\TenantResource;
use App\Filament\Support\FormatsMoney;
use BackedEnum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class FinancialTransactionResource extends TenantResource
{
    protected static ?string $model = FinancialTransaction::class;

    protected static string $viewPermission = FoundationPermission::TransactionsView->value;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-scale';

    protected static string|\UnitEnum|null $navigationGroup = 'Accounting';

    protected static ?int $navigationSort = 30;

    protected static ?string $recordTitleAttribute = 'transaction_number';

    /** @return Builder<FinancialTransaction> */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<FinancialTransaction> $query */
        $query = parent::getEloquentQuery();

        return $query
            ->with(['branch', 'postedBy'])
            ->withSum(['entries as total_debit_minor' => fn (Builder $query) => $query->where('entry_type', 'debit')], 'amount_minor')
            ->withSum(['entries as total_credit_minor' => fn (Builder $query) => $query->where('entry_type', 'credit')], 'amount_minor');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('transaction_number')->label('Transaction')->searchable()->sortable(),
                TextColumn::make('transaction_date')->date()->sortable(),
                TextColumn::make('transaction_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): string => str((string) ($state instanceof BackedEnum ? $state->value : $state))->headline()->toString()),
                TextColumn::make('description')->limit(50)->wrap(),
                TextColumn::make('total_debit_minor')
                    ->label('Debit')
                    ->formatStateUsing(fn (mixed $state, FinancialTransaction $record): string => FormatsMoney::format((int) $state, $record->currency_code)),
                TextColumn::make('total_credit_minor')
                    ->label('Credit')
                    ->formatStateUsing(fn (mixed $state, FinancialTransaction $record): string => FormatsMoney::format((int) $state, $record->currency_code)),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): string => str((string) ($state instanceof BackedEnum ? $state->value : $state))->headline()->toString()),
                TextColumn::make('branch.name')->placeholder('Organisation-wide')->toggleable()->visibleFrom('lg'),
                TextColumn::make('postedBy.name')->label('Posted by')->toggleable()->visibleFrom('xl'),
            ])
            ->defaultSort('transaction_date', 'desc')
            ->paginated([10, 25, 50]);
    }

    public static function getPages(): array
    {
        return ['index' => ListFinancialTransactions::route('/')];
    }
}
