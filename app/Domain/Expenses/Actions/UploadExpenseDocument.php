<?php

namespace App\Domain\Expenses\Actions;

use App\Domain\Expenses\Models\ExpenseDocument;
use App\Domain\Expenses\Models\ExpenseRequest;
use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UploadExpenseDocument
{
    public function __construct(private RoleAuthorization $authorization) {}

    public function handle(ExpenseRequest $expense, User $actor, string $type, UploadedFile $file): ExpenseDocument
    {
        $this->authorization->authorize(FoundationPermission::ExpenseRequestsUploadDocuments->value);
        $disk = 'local';
        $storedName = Str::uuid().'.'.$file->guessExtension();
        $path = $file->storeAs("expenses/{$expense->organisation->public_id}/{$expense->public_id}", $storedName, $disk);
        if ($path === false) {
            throw new \RuntimeException('Document storage failed.');
        }
        try {
            return DB::transaction(fn () => ExpenseDocument::query()->create(['organisation_id' => $expense->organisation_id, 'documentable_type' => 'expense_request', 'documentable_id' => $expense->getKey(), 'document_type' => $type, 'original_name' => $file->getClientOriginalName(), 'stored_name' => $storedName, 'disk' => $disk, 'path' => $path, 'mime_type' => $file->getMimeType() ?? 'application/octet-stream', 'size_bytes' => $file->getSize(), 'checksum' => hash_file('sha256', $file->getRealPath()), 'uploaded_by_user_id' => $actor->getKey()]));
        } catch (\Throwable $e) {
            Storage::disk($disk)->delete($path);
            throw $e;
        }
    }
}
