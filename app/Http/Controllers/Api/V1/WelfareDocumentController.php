<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Domain\Welfare\Models\WelfareClaim;
use App\Domain\Welfare\Models\WelfareDocument;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WelfareDocumentController extends Controller
{
    public function index(string $claimUuid, TenantContext $context, RoleAuthorization $authorization): AnonymousResourceCollection
    {
        $authorization->authorize(FoundationPermission::WelfareDocumentsView->value);

        return JsonResource::collection($this->claim($context, $claimUuid)->documents()->latest()->get());
    }

    public function store(Request $request, string $claimUuid, TenantContext $context, RoleAuthorization $authorization): JsonResource
    {
        $authorization->authorize(FoundationPermission::WelfareDocumentsUpload->value);
        $data = $request->validate(['document_type' => ['required', 'string', 'max:50'], 'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240']]);
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        $claim = $this->claim($context, $claimUuid);
        $file = $request->file('file');
        $path = $file->store('welfare/'.$context->organisation()->public_id.'/'.$claim->public_id, 'local');
        abort_if($path === false, 500);
        $checksum = hash_file('sha256', $file->getRealPath());
        abort_if($checksum === false, 500);
        $document = WelfareDocument::query()->create(['organisation_id' => $claim->organisation_id, 'welfare_claim_id' => $claim->getKey(), 'document_type' => $data['document_type'], 'disk' => 'local', 'path' => $path, 'original_name' => Str::limit($file->getClientOriginalName(), 255, ''), 'mime_type' => $file->getMimeType() ?: 'application/octet-stream', 'size_bytes' => $file->getSize(), 'checksum_sha256' => $checksum, 'uploaded_by_user_id' => $actor->getKey()]);

        return new JsonResource($document);
    }

    public function download(string $claimUuid, string $documentUuid, TenantContext $context, RoleAuthorization $authorization): StreamedResponse
    {
        $authorization->authorize(FoundationPermission::WelfareDocumentsView->value);
        $document = $this->claim($context, $claimUuid)->documents()->where('public_id', $documentUuid)->firstOrFail();

        return Storage::disk($document->disk)->download($document->path, $document->original_name);
    }

    public function verify(Request $request, string $claimUuid, string $documentUuid, TenantContext $context, RoleAuthorization $authorization): JsonResource
    {
        $authorization->authorize(FoundationPermission::WelfareClaimsVerify->value);
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        $document = $this->claim($context, $claimUuid)->documents()->where('public_id', $documentUuid)->firstOrFail();
        $document->update(['verification_status' => 'verified', 'verified_by_user_id' => $actor->getKey(), 'verified_at' => now()]);

        return new JsonResource($document->refresh());
    }

    private function claim(TenantContext $context, string $uuid): WelfareClaim
    {
        return WelfareClaim::query()->where('organisation_id', $context->organisation()->getKey())->when($context->branch(), fn ($query, $branch) => $query->where('branch_id', $branch->getKey()))->where('public_id', $uuid)->firstOrFail();
    }
}
