<?php

namespace App\Filament\Resources\Receipts;

use App\Domain\Collections\Actions\PrepareReceiptReissue;
use App\Domain\Collections\Enums\ReceiptStatus;
use App\Domain\Collections\Enums\ReceiptType;
use App\Domain\Collections\Exceptions\InvalidReceiptOperation;
use App\Domain\Collections\Models\Receipt;
use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Enums\SystemRole;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Filament\Resources\Receipts\Pages\ListReceipts;
use App\Filament\Resources\Receipts\Pages\ViewReceipt;
use App\Filament\Resources\TenantResource;
use App\Filament\Support\FormatsMoney;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReceiptResource extends TenantResource
{
    protected static ?string $model = Receipt::class;

    protected static string $viewPermission = FoundationPermission::ReceiptsView->value;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-receipt-percent';

    protected static string|\UnitEnum|null $navigationGroup = 'Contributions';

    protected static ?int $navigationSort = 40;

    protected static ?string $recordTitleAttribute = 'receipt_number';

    protected static bool $isGloballySearchable = true;

    /** @return list<string> */
    public static function getGloballySearchableAttributes(): array
    {
        return [
            'receipt_number',
            'public_id',
            'member.membership_number',
            'payer_name',
        ];
    }

    /** @return Builder<Receipt> */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<Receipt> $query */
        $query = parent::getEloquentQuery();
        $context = app(TenantContext::class);

        if ($context->role()->name === SystemRole::Collector->value) {
            $query->whereHas(
                'payment',
                fn (Builder $query): Builder => $query->where(
                    'collected_by_user_id',
                    $context->roleAssignment()->user_id,
                ),
            );
        }

        return $query->with([
            'branch',
            'member',
            'issuedBy',
            'payment',
            'loanRepayment',
            'welfareBenefitPayment',
            'withdrawalPayout',
            'financialTransaction',
            'originalReceipt',
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Receipt')
                ->schema([
                    TextEntry::make('receipt_number')->label('Receipt')->copyable(),
                    TextEntry::make('receipt_type')
                        ->label('Type')
                        ->badge()
                        ->formatStateUsing(self::formatState(...)),
                    TextEntry::make('status')
                        ->badge()
                        ->formatStateUsing(self::formatState(...)),
                    TextEntry::make('amount_minor')
                        ->label('Amount')
                        ->state(fn (Receipt $record): string => FormatsMoney::format(
                            $record->amount_minor,
                            $record->currency_code,
                        )),
                    TextEntry::make('payment_method')
                        ->label('Method')
                        ->formatStateUsing(self::formatState(...)),
                    TextEntry::make('issued_at')->dateTime(),
                    TextEntry::make('issuedBy.name')->label('Issued by'),
                    TextEntry::make('branch.name')->placeholder('Organisation-wide'),
                    TextEntry::make('public_id')
                        ->label('Receipt UUID')
                        ->copyable()
                        ->columnSpanFull(),
                ])
                ->columns(['sm' => 2, 'lg' => 3]),
            Section::make('Member and payer')
                ->schema([
                    TextEntry::make('member.membership_number')
                        ->label('Member number')
                        ->copyable(),
                    TextEntry::make('member_name')
                        ->label('Member')
                        ->state(fn (Receipt $record): string => $record->member->fullName()),
                    TextEntry::make('payer_name')->placeholder('Member'),
                ])
                ->columns(['md' => 3]),
            Section::make('Transaction trace')
                ->description('Receipts are read-only evidence of posted financial transactions.')
                ->schema([
                    TextEntry::make('source_reference')
                        ->label('Source record')
                        ->state(self::sourceReference(...))
                        ->placeholder('Unavailable'),
                    TextEntry::make('financialTransaction.transaction_number')
                        ->label('Financial transaction')
                        ->copyable(),
                    TextEntry::make('originalReceipt.receipt_number')
                        ->label('Original receipt')
                        ->placeholder('Original issue'),
                ])
                ->columns(['md' => 3]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('receipt_number')
                    ->label('Receipt')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('member.membership_number')
                    ->label('Member')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('amount_minor')
                    ->label('Amount')
                    ->state(fn (Receipt $record): string => FormatsMoney::format(
                        $record->amount_minor,
                        $record->currency_code,
                    )),
                TextColumn::make('receipt_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(self::formatState(...))
                    ->visibleFrom('md'),
                TextColumn::make('issued_at')
                    ->dateTime()
                    ->sortable()
                    ->visibleFrom('md'),
                TextColumn::make('payment_method')
                    ->label('Method')
                    ->formatStateUsing(self::formatState(...))
                    ->visibleFrom('lg'),
                TextColumn::make('branch.name')
                    ->placeholder('Organisation-wide')
                    ->visibleFrom('lg'),
                TextColumn::make('issuedBy.name')
                    ->label('Issued by')
                    ->visibleFrom('xl'),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(self::formatState(...)),
            ])
            ->filters([
                SelectFilter::make('receipt_type')->options(self::typeOptions()),
                SelectFilter::make('status')->options(self::statusOptions()),
            ])
            ->recordActions([
                ViewAction::make(),
                self::reissueAction(),
            ])
            ->defaultSort('issued_at', 'desc')
            ->paginated([10, 25, 50]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReceipts::route('/'),
            'view' => ViewReceipt::route('/{record}'),
        ];
    }

    /** @return array<string, string> */
    private static function typeOptions(): array
    {
        return collect(ReceiptType::cases())
            ->mapWithKeys(fn (ReceiptType $type): array => [
                $type->value => Str::headline($type->value),
            ])
            ->all();
    }

    /** @return array<string, string> */
    private static function statusOptions(): array
    {
        return collect(ReceiptStatus::cases())
            ->mapWithKeys(fn (ReceiptStatus $status): array => [
                $status->value => Str::headline($status->value),
            ])
            ->all();
    }

    private static function sourceReference(Receipt $record): ?string
    {
        return match ($record->receipt_type) {
            ReceiptType::ContributionPayment,
            ReceiptType::ContributionReversal => $record->payment?->payment_number,
            ReceiptType::LoanRepayment => $record->loanRepayment?->repayment_number,
            ReceiptType::WelfareBenefitPayment => $record
                ->welfareBenefitPayment
                ?->payment_number,
            ReceiptType::WithdrawalPayout => $record
                ->withdrawalPayout
                ?->payout_number,
        };
    }

    private static function formatState(mixed $state): string
    {
        return Str::headline((string) ($state instanceof BackedEnum ? $state->value : $state));
    }

    public static function reissueAction(): Action
    {
        return Action::make('reissue')
            ->label('Reissue')
            ->icon('heroicon-o-printer')
            ->requiresConfirmation()
            ->modalDescription(
                'Download a printable copy containing the receipt verification code.',
            )
            ->visible(fn (
                RoleAuthorization $authorization,
            ): bool => $authorization->can(
                FoundationPermission::ReceiptsReissue->value,
            ))
            ->action(function (
                Receipt $record,
                PrepareReceiptReissue $prepareReissue,
            ): StreamedResponse {
                try {
                    $prepared = $prepareReissue->handle($record);
                } catch (InvalidReceiptOperation $exception) {
                    throw ValidationException::withMessages([
                        'receipt' => $exception->getMessage(),
                    ]);
                }

                $html = view('receipts.print', [
                    'receiptView' => $prepared['view'],
                    'reissuedAt' => $prepared['reissued_at'],
                ])->render();

                return response()->streamDownload(
                    static function () use ($html): void {
                        echo $html;
                    },
                    Str::slug($record->receipt_number).'.html',
                    ['Content-Type' => 'text/html; charset=UTF-8'],
                );
            });
    }
}
