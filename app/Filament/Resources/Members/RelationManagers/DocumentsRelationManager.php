<?php

namespace App\Filament\Resources\Members\RelationManagers;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Membership\Actions\ReviewMemberDocument;
use App\Domain\Membership\Actions\UploadMemberDocument;
use App\Domain\Membership\Enums\DocumentVerificationStatus;
use App\Domain\Membership\Enums\MemberDocumentType;
use App\Domain\Membership\Exceptions\InvalidMembershipOperation;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Models\MemberDocument;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Filament\Resources\Members\MemberResource;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Concerns\RestrictsFileUploadsToSchemaComponents;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DocumentsRelationManager extends RelationManager
{
    use RestrictsFileUploadsToSchemaComponents;

    protected static string $relationship = 'documents';

    protected static ?string $title = 'Private documents';

    public function isReadOnly(): bool
    {
        return false;
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof Member
            && MemberResource::canView($ownerRecord)
            && app(RoleAuthorization::class)
                ->can(FoundationPermission::MembersViewDocuments->value);
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Document metadata')
                ->description('Storage paths and checksums are intentionally hidden.')
                ->schema([
                    TextEntry::make('original_name')->label('File name'),
                    TextEntry::make('document_type')
                        ->label('Type')
                        ->formatStateUsing(self::formatState(...)),
                    TextEntry::make('mime_type')->label('Media type'),
                    TextEntry::make('size_bytes')
                        ->label('Size')
                        ->formatStateUsing(fn (mixed $state): string => Number::fileSize(
                            is_numeric($state) ? (int) $state : 0,
                        )),
                    TextEntry::make('verification_status')
                        ->label('Status')
                        ->badge()
                        ->formatStateUsing(self::formatState(...)),
                    TextEntry::make('uploadedBy.name')->label('Uploaded by'),
                    TextEntry::make('verifiedBy.name')
                        ->label('Reviewed by')
                        ->placeholder('Not reviewed'),
                    TextEntry::make('verified_at')->dateTime()->placeholder('Not reviewed'),
                    TextEntry::make('rejection_reason')
                        ->placeholder('Not rejected')
                        ->columnSpanFull(),
                    TextEntry::make('public_id')
                        ->label('Document UUID')
                        ->copyable()
                        ->columnSpanFull(),
                ])
                ->columns(['md' => 2]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->where('organisation_id', $this->member()->organisation_id)
                ->with(['uploadedBy', 'verifiedBy']))
            ->columns([
                TextColumn::make('original_name')
                    ->label('File name')
                    ->searchable()
                    ->wrap()
                    ->weight('medium'),
                TextColumn::make('document_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(self::formatState(...)),
                TextColumn::make('verification_status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(self::formatState(...)),
                TextColumn::make('size_bytes')
                    ->label('Size')
                    ->formatStateUsing(fn (mixed $state): string => Number::fileSize(
                        is_numeric($state) ? (int) $state : 0,
                    ))
                    ->visibleFrom('md'),
                TextColumn::make('uploadedBy.name')
                    ->label('Uploaded by')
                    ->visibleFrom('lg'),
                TextColumn::make('created_at')
                    ->label('Uploaded')
                    ->dateTime()
                    ->sortable()
                    ->visibleFrom('md'),
            ])
            ->filters([
                SelectFilter::make('document_type')->options(self::typeOptions()),
                SelectFilter::make('verification_status')->options(self::statusOptions()),
            ])
            ->headerActions([
                Action::make('uploadDocument')
                    ->label('Upload document')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->visible(fn (RoleAuthorization $authorization): bool => $authorization
                        ->can(FoundationPermission::MembersUploadDocuments->value))
                    ->schema([
                        Select::make('document_type')
                            ->label('Document type')
                            ->options(self::typeOptions())
                            ->enum(MemberDocumentType::class)
                            ->required(),
                        FileUpload::make('file')
                            ->label('Private file')
                            ->acceptedFileTypes([
                                'application/pdf',
                                'image/jpeg',
                                'image/png',
                                'image/webp',
                            ])
                            ->maxSize(10240)
                            ->storeFiles(false)
                            ->previewable(false)
                            ->required()
                            ->helperText('PDF, JPEG, PNG, or WebP. Maximum 10 MB.'),
                    ])
                    ->modalDescription(
                        'The file is stored privately and can only be downloaded after authorization.',
                    )
                    ->action(function (
                        array $data,
                        UploadMemberDocument $uploadDocument,
                        RoleAuthorization $authorization,
                        TenantContext $tenantContext,
                    ): void {
                        $authorization->authorize(
                            FoundationPermission::MembersUploadDocuments->value,
                        );
                        $file = $data['file'] ?? null;
                        $type = $data['document_type'] ?? null;

                        if (! $file instanceof UploadedFile) {
                            throw ValidationException::withMessages([
                                'file' => 'A valid private document is required.',
                            ]);
                        }

                        try {
                            $uploadDocument->handle(
                                $this->member(),
                                $tenantContext->roleAssignment()->user,
                                $type instanceof MemberDocumentType
                                    ? $type->value
                                    : (string) $type,
                                $file,
                            );
                        } catch (InvalidMembershipOperation $exception) {
                            throw self::validationException('file', $exception);
                        }

                        Notification::make()->title('Document uploaded')->success()->send();
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->visible(fn (RoleAuthorization $authorization): bool => $authorization
                        ->can(FoundationPermission::MembersViewDocuments->value))
                    ->url(fn (MemberDocument $record): string => route(
                        'api.v1.members.documents.download',
                        [$this->member()->public_id, $record->public_id],
                    )),
                Action::make('verify')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (
                        MemberDocument $record,
                        RoleAuthorization $authorization,
                    ): bool => $record->verification_status === DocumentVerificationStatus::Pending
                        && $authorization->can(
                            FoundationPermission::MembersVerifyDocuments->value,
                        ))
                    ->action(function (
                        MemberDocument $record,
                        ReviewMemberDocument $reviewDocument,
                        RoleAuthorization $authorization,
                        TenantContext $tenantContext,
                    ): void {
                        $authorization->authorize(
                            FoundationPermission::MembersVerifyDocuments->value,
                        );
                        $this->review(
                            $record,
                            DocumentVerificationStatus::Verified,
                            null,
                            $reviewDocument,
                            $tenantContext,
                        );
                    }),
                Action::make('reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->schema([
                        Textarea::make('reason')
                            ->label('Rejection reason')
                            ->required()
                            ->maxLength(2000),
                    ])
                    ->visible(fn (
                        MemberDocument $record,
                        RoleAuthorization $authorization,
                    ): bool => $record->verification_status === DocumentVerificationStatus::Pending
                        && $authorization->can(
                            FoundationPermission::MembersVerifyDocuments->value,
                        ))
                    ->action(function (
                        MemberDocument $record,
                        array $data,
                        ReviewMemberDocument $reviewDocument,
                        RoleAuthorization $authorization,
                        TenantContext $tenantContext,
                    ): void {
                        $authorization->authorize(
                            FoundationPermission::MembersVerifyDocuments->value,
                        );
                        $reason = $data['reason'] ?? null;

                        if (! is_string($reason) || trim($reason) === '') {
                            throw ValidationException::withMessages([
                                'reason' => 'A rejection reason is required.',
                            ]);
                        }

                        $this->review(
                            $record,
                            DocumentVerificationStatus::Rejected,
                            trim($reason),
                            $reviewDocument,
                            $tenantContext,
                        );
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([5, 10, 25]);
    }

    private function review(
        MemberDocument $document,
        DocumentVerificationStatus $status,
        ?string $reason,
        ReviewMemberDocument $reviewDocument,
        TenantContext $tenantContext,
    ): void {
        try {
            $reviewDocument->handle(
                $this->member(),
                $document,
                $tenantContext->roleAssignment()->user,
                $status,
                $reason,
            );
        } catch (InvalidMembershipOperation $exception) {
            throw self::validationException('reason', $exception);
        }

        Notification::make()
            ->title($status === DocumentVerificationStatus::Verified
                ? 'Document verified'
                : 'Document rejected')
            ->success()
            ->send();
    }

    private function member(): Member
    {
        $owner = $this->getOwnerRecord();

        if (! $owner instanceof Member) {
            throw new \LogicException('The document relation owner must be a member.');
        }

        return $owner;
    }

    /** @return array<string, string> */
    private static function typeOptions(): array
    {
        return collect(MemberDocumentType::cases())
            ->mapWithKeys(fn (MemberDocumentType $type): array => [
                $type->value => Str::headline($type->value),
            ])
            ->all();
    }

    /** @return array<string, string> */
    private static function statusOptions(): array
    {
        return collect(DocumentVerificationStatus::cases())
            ->mapWithKeys(fn (DocumentVerificationStatus $status): array => [
                $status->value => Str::headline($status->value),
            ])
            ->all();
    }

    private static function formatState(mixed $state): string
    {
        return Str::headline((string) ($state instanceof \BackedEnum ? $state->value : $state));
    }

    private static function validationException(
        string $field,
        InvalidMembershipOperation $exception,
    ): ValidationException {
        return ValidationException::withMessages([
            $field => $exception->getMessage() !== ''
                ? $exception->getMessage()
                : 'The member document operation is invalid.',
        ]);
    }
}
