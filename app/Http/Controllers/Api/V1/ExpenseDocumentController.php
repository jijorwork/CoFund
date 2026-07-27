<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Expenses\Actions\UploadExpenseDocument;
use App\Domain\Expenses\Models\ExpenseDocument;
use App\Domain\Expenses\Models\ExpenseRequest;
use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UploadExpenseDocumentRequest;
use App\Http\Resources\Api\V1\ExpenseDocumentResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExpenseDocumentController extends Controller
{
    public function index(Request $request, string $expenseUuid, TenantContext $context, RoleAuthorization $authorization): AnonymousResourceCollection
    {
        $authorization->authorize(FoundationPermission::ExpenseRequestsViewDocuments->value);

        return ExpenseDocumentResource::collection($this->expense($context, $expenseUuid)->documents()->latest()->paginate(25));
    }

    public function store(UploadExpenseDocumentRequest $request, string $expenseUuid, TenantContext $context, UploadExpenseDocument $action): ExpenseDocumentResource
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);

        return ExpenseDocumentResource::make($action->handle($this->expense($context, $expenseUuid), $actor, $request->validated('document_type'), $request->file('file')));
    }

    public function show(Request $request, string $expenseUuid, string $documentUuid, TenantContext $context, RoleAuthorization $authorization): ExpenseDocumentResource
    {
        $authorization->authorize(FoundationPermission::ExpenseRequestsViewDocuments->value);

        return ExpenseDocumentResource::make($this->document($context, $expenseUuid, $documentUuid));
    }

    public function download(Request $request, string $expenseUuid, string $documentUuid, TenantContext $context, RoleAuthorization $authorization): StreamedResponse
    {
        $authorization->authorize(FoundationPermission::ExpenseRequestsViewDocuments->value);
        $document = $this->document($context, $expenseUuid, $documentUuid);

        return Storage::disk($document->disk)->download($document->path, $document->original_name);
    }

    private function expense(TenantContext $context, string $uuid): ExpenseRequest
    {
        return ExpenseRequest::query()->whereBelongsTo($context->organisation())->when($context->roleAssignment()->branch_id !== null, fn ($q) => $q->where('branch_id', $context->branch()?->getKey()))->where('public_id', $uuid)->firstOrFail();
    }

    private function document(TenantContext $context, string $expenseUuid, string $uuid): ExpenseDocument
    {
        return $this->expense($context, $expenseUuid)->documents()->where('public_id', $uuid)->firstOrFail();
    }
}
