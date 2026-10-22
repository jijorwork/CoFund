<?php

namespace App\Filament\Platform\Resources\SubscriptionPlans;

use App\Domain\Subscriptions\Models\SubscriptionPlan;
use App\Filament\Platform\Resources\SubscriptionPlans\Pages\CreateSubscriptionPlan;
use App\Filament\Platform\Resources\SubscriptionPlans\Pages\EditSubscriptionPlan;
use App\Filament\Platform\Resources\SubscriptionPlans\Pages\ListSubscriptionPlans;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SubscriptionPlanResource extends Resource
{
    protected static ?string $model = SubscriptionPlan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('code')->required()->maxLength(50)->unique(ignoreRecord: true),
                Textarea::make('description')->maxLength(2000),
                TextInput::make('monthly_price_minor')->label('Monthly price (minor units)')->numeric()->minValue(0)->required(),
                TextInput::make('yearly_price_minor')->label('Yearly price (minor units)')->numeric()->minValue(0)->required(),
                TextInput::make('currency_code')->default('GHS')->required()->length(3),
                TextInput::make('grace_days')->numeric()->minValue(0)->default(0)->required(),
                Toggle::make('is_active')->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('code')->searchable(),
                TextColumn::make('monthly_price_minor')->label('Monthly')->money(fn ($record): string => $record->currency_code, divideBy: 100),
                TextColumn::make('yearly_price_minor')->label('Yearly')->money(fn ($record): string => $record->currency_code, divideBy: 100),
                TextColumn::make('grace_days')->label('Grace days')->numeric(),
                TextColumn::make('is_active')->label('Active')->badge(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSubscriptionPlans::route('/'),
            'create' => CreateSubscriptionPlan::route('/create'),
            'edit' => EditSubscriptionPlan::route('/{record}/edit'),
        ];
    }
}
