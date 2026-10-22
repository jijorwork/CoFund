<?php

namespace App\Filament\Platform\Resources\OrganisationSubscriptions;

use App\Domain\Subscriptions\Actions\GenerateSubscriptionRenewalInvoice;
use App\Domain\Subscriptions\Actions\RecordManualSubscriptionPayment;
use App\Domain\Subscriptions\Enums\InvoiceStatus;
use App\Domain\Subscriptions\Enums\ManualPaymentMethod;
use App\Domain\Subscriptions\Models\OrganisationSubscription;
use App\Filament\Platform\Resources\OrganisationSubscriptions\Pages\ListOrganisationSubscriptions;
use App\Filament\Platform\Resources\OrganisationSubscriptions\Pages\ViewOrganisationSubscription;
use App\Models\User;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class OrganisationSubscriptionResource extends Resource
{
    protected static ?string $model = OrganisationSubscription::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static ?string $recordTitleAttribute = 'public_id';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    /** @return Builder<OrganisationSubscription> */
    public static function getEloquentQuery(): Builder
    {
        return OrganisationSubscription::query()
            ->with(['organisation', 'plan', 'invoices'])
            ->latest();
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Subscription')
                ->schema([
                    TextEntry::make('organisation.name')->label('Organisation'),
                    TextEntry::make('plan.name')->label('Plan'),
                    TextEntry::make('billing_interval')->badge(),
                    TextEntry::make('status')->badge(),
                    TextEntry::make('current_period_starts_on')->date(),
                    TextEntry::make('current_period_ends_on')->date(),
                    TextEntry::make('grace_ends_on')->date()->placeholder('None'),
                    TextEntry::make('invoices.0.invoice_number')->label('Latest invoice')->placeholder('None'),
                ])->columns(['md' => 2]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('organisation.name')->searchable()->sortable(),
            TextColumn::make('plan.name')->label('Plan'),
            TextColumn::make('billing_interval')->badge(),
            TextColumn::make('status')->badge(),
            TextColumn::make('current_period_ends_on')->label('Period ends')->date()->sortable(),
            TextColumn::make('invoices.0.status')->label('Invoice')->badge(),
        ])->recordActions([
            ViewAction::make(),
            Action::make('generateRenewal')
                ->label('Generate renewal')
                ->icon(Heroicon::OutlinedDocumentPlus)
                ->requiresConfirmation()
                ->visible(fn (OrganisationSubscription $record): bool => ! $record->invoices()
                    ->whereIn('status', [
                        InvoiceStatus::Issued,
                        InvoiceStatus::PartiallyPaid,
                        InvoiceStatus::Overdue,
                    ])->exists())
                ->action(function (OrganisationSubscription $record): void {
                    $actor = auth()->user();
                    abort_unless($actor instanceof User, 403);
                    app(GenerateSubscriptionRenewalInvoice::class)->handle($actor, $record);
                }),
            Action::make('recordPayment')
                ->label('Record payment')
                ->icon(Heroicon::OutlinedBanknotes)
                ->visible(fn (OrganisationSubscription $record): bool => in_array(
                    $record->invoices->first()?->status,
                    [InvoiceStatus::Issued, InvoiceStatus::PartiallyPaid, InvoiceStatus::Overdue],
                    true,
                ))
                ->form([
                    TextInput::make('payment_reference')->required()->maxLength(255),
                    Select::make('payment_method')->options(
                        collect(ManualPaymentMethod::cases())->mapWithKeys(
                            fn (ManualPaymentMethod $method): array => [$method->value => str($method->value)->headline()->toString()],
                        )->all(),
                    )->required(),
                    TextInput::make('amount_minor')->label('Amount (minor units)')->numeric()->minValue(1)->required(),
                    DatePicker::make('paid_on')->default(today())->required(),
                    Textarea::make('notes')->maxLength(2000),
                ])
                ->action(function (OrganisationSubscription $record, array $data): void {
                    $actor = auth()->user();
                    abort_unless($actor instanceof User, 403);
                    $invoice = $record->invoices()->latest()->firstOrFail();
                    app(RecordManualSubscriptionPayment::class)->handle(
                        $actor,
                        $invoice,
                        (string) $data['payment_reference'],
                        ManualPaymentMethod::from((string) $data['payment_method']),
                        (int) $data['amount_minor'],
                        CarbonImmutable::parse((string) $data['paid_on']),
                        isset($data['notes']) ? (string) $data['notes'] : null,
                    );
                }),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrganisationSubscriptions::route('/'),
            'view' => ViewOrganisationSubscription::route('/{record}'),
        ];
    }
}
