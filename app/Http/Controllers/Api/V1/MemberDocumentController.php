<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Membership\Actions\ReviewMemberDocument;
use App\Domain\Membership\Actions\UploadMemberDocument;
use App\Domain\Membership\Enums\DocumentVerificationStatus;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Models\MemberDocument;
use App\Domain\Membership\Queries\FindMemberByUuid;
use App\Domain\Membership\Queries\ListMemberDocuments;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ReviewMemberDocumentRequest;
use App\Http\Requests\Api\V1\UploadMemberDocumentRequest;
use App\Http\Resources\Api\V1\MemberDocumentResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MemberDocumentController extends Controller
{
    public function index(Request $request, string $memberUuid, FindMemberByUuid $members, ListMemberDocuments $documents, RoleAuthorization $authorization): AnonymousResourceCollection
    {
        $authorization->authorize(FoundationPermission::MembersViewDocuments->value);
        $member = $members->handle($memberUuid) ?? abort(404);

        return MemberDocumentResource::collection($documents->handle($member))
            ->additional(['meta' => ['request_id' => ApiResponse::requestId($request)]]);
    }

    public function store(UploadMemberDocumentRequest $request, string $memberUuid, FindMemberByUuid $members, UploadMemberDocument $action): MemberDocumentResource
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        $file = $request->file('file');
        abort_if($file === null, 422);

        return MemberDocumentResource::make($action->handle(
            $members->handle($memberUuid) ?? abort(404),
            $actor,
            $request->validated('document_type'),
            $file,
        ))->additional(['meta' => ['request_id' => ApiResponse::requestId($request)]]);
    }

    public function show(Request $request, string $memberUuid, string $documentUuid, FindMemberByUuid $members, RoleAuthorization $authorization): MemberDocumentResource
    {
        $authorization->authorize(FoundationPermission::MembersViewDocuments->value);
        $member = $members->handle($memberUuid) ?? abort(404);

        return MemberDocumentResource::make($this->document($member, $documentUuid))
            ->additional(['meta' => ['request_id' => ApiResponse::requestId($request)]]);
    }

    public function download(Request $request, string $memberUuid, string $documentUuid, FindMemberByUuid $members, RoleAuthorization $authorization): StreamedResponse
    {
        $authorization->authorize(FoundationPermission::MembersViewDocuments->value);
        $member = $members->handle($memberUuid) ?? abort(404);
        $document = $this->document($member, $documentUuid);

        return Storage::disk($document->disk)->download(
            $document->path,
            $document->original_name,
            ['Content-Type' => $document->mime_type],
        );
    }

    public function verify(ReviewMemberDocumentRequest $request, string $memberUuid, string $documentUuid, FindMemberByUuid $members, ReviewMemberDocument $action): MemberDocumentResource
    {
        return $this->review($request, $memberUuid, $documentUuid, $members, $action, DocumentVerificationStatus::Verified);
    }

    public function reject(ReviewMemberDocumentRequest $request, string $memberUuid, string $documentUuid, FindMemberByUuid $members, ReviewMemberDocument $action): MemberDocumentResource
    {
        return $this->review($request, $memberUuid, $documentUuid, $members, $action, DocumentVerificationStatus::Rejected);
    }

    private function review(ReviewMemberDocumentRequest $request, string $memberUuid, string $documentUuid, FindMemberByUuid $members, ReviewMemberDocument $action, DocumentVerificationStatus $status): MemberDocumentResource
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        $member = $members->handle($memberUuid) ?? abort(404);

        return MemberDocumentResource::make($action->handle(
            $member,
            $this->document($member, $documentUuid),
            $actor,
            $status,
            $request->validated('reason'),
        ))->additional(['meta' => ['request_id' => ApiResponse::requestId($request)]]);
    }

    private function document(Member $member, string $uuid): MemberDocument
    {
        return MemberDocument::query()
            ->where('organisation_id', $member->organisation_id)
            ->where('documentable_type', 'member')
            ->where('documentable_id', $member->getKey())
            ->where('public_id', $uuid)
            ->first() ?? abort(404);
    }
}
