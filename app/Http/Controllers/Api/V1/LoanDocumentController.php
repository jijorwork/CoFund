<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Loans\Models\LoanApplication;
use App\Domain\Loans\Models\LoanDocument;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LoanDocumentController extends Controller
{
    public function index(string $applicationUuid, TenantContext $context, RoleAuthorization $authorization): AnonymousResourceCollection
    {
        $authorization->authorize(FoundationPermission::LoanDocumentsView->value);

        return JsonResource::collection($this->application($context, $applicationUuid)->documents()->latest()->get());
    }

    public function store(Request $request, string $applicationUuid, TenantContext $context, RoleAuthorization $authorization): JsonResource
    {
        $authorization->authorize(FoundationPermission::LoanDocumentsUpload->value);
        $data = $request->validate(['document_type' => ['required', 'string', 'max:50'], 'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240']]);
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        $application = $this->application($context, $applicationUuid);
        $file = $request->file('file');
        $path = $file->store('loans/'.$context->organisation()->public_id.'/'.$application->public_id, 'local');
        abort_if($path === false, 500);
        $checksum = hash_file('sha256', $file->getRealPath());
        abort_if($checksum === false, 500);
        $document = LoanDocument::query()->create(['organisation_id' => $application->organisation_id, 'loan_application_id' => $application->getKey(), 'documentable_type' => 'loan_application', 'documentable_id' => $application->getKey(), 'document_type' => $data['document_type'], 'disk' => 'local', 'path' => $path, 'original_name' => Str::limit($file->getClientOriginalName(), 255, ''), 'mime_type' => $file->getMimeType() ?: 'application/octet-stream', 'size_bytes' => $file->getSize(), 'checksum_sha256' => $checksum, 'uploaded_by_user_id' => $actor->getKey()]);

        return new JsonResource($document->makeHidden(['disk', 'path']));
    }

    public function download(string $applicationUuid, string $documentUuid, TenantContext $context, RoleAuthorization $authorization): StreamedResponse
    {
        $authorization->authorize(FoundationPermission::LoanDocumentsView->value);
        $application = $this->application($context, $applicationUuid);
        $document = $application->documents()->where('public_id', $documentUuid)->firstOrFail();

        return Storage::disk($document->disk)->download($document->path, $document->original_name);
    }

    private function application(TenantContext $context, string $uuid): LoanApplication
    {
        return LoanApplication::query()->whereBelongsTo($context->organisation())->where('public_id', $uuid)->firstOrFail();
    }
}
