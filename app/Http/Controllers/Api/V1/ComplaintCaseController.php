<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Complaints\Actions\ManageComplaintCase;
use App\Domain\Complaints\Models\ComplaintCase;
use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Membership\Models\Member;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ComplaintCaseResource;
use App\Http\Resources\Api\V1\ComplaintMessageResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ComplaintCaseController extends Controller
{
    public function index(Request $request, TenantContext $context, RoleAuthorization $authorization): AnonymousResourceCollection
    {
        $authorization->authorize(FoundationPermission::ComplaintsView->value);
        $filters = $request->validate([
            'status' => ['sometimes', 'in:open,in_review,resolved,closed'],
            'member_uuid' => ['sometimes', 'uuid'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        $query = $this->scopedQuery($context)->with(['member', 'reportedBy', 'assignedTo']);

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (isset($filters['member_uuid'])) {
            $query->whereHas('member', fn (Builder $query) => $query->where('public_id', $filters['member_uuid']));
        }

        return ComplaintCaseResource::collection($query->latest('id')->paginate($filters['per_page'] ?? 25)->withQueryString())
            ->additional(['meta' => ['request_id' => ApiResponse::requestId($request)]]);
    }

    public function show(Request $request, string $complaintUuid, TenantContext $context, RoleAuthorization $authorization): ComplaintCaseResource
    {
        $authorization->authorize(FoundationPermission::ComplaintsView->value);

        return $this->response($request, $this->find($context, $complaintUuid)->load(['member', 'reportedBy', 'assignedTo', 'messages.author']));
    }

    public function store(Request $request, TenantContext $context, ManageComplaintCase $action): JsonResponse
    {
        $data = $request->validate([
            'member_uuid' => ['nullable', 'uuid'],
            'subject' => ['required', 'string', 'min:3', 'max:200'],
            'category' => ['required', 'string', 'max:80'],
            'body' => ['required', 'string', 'min:3', 'max:20000'],
        ]);
        $member = isset($data['member_uuid'])
            ? Member::query()->where('organisation_id', $context->organisation()->getKey())->where('public_id', $data['member_uuid'])->firstOrFail()
            : null;
        $case = $action->open($this->actor($request), $member, $data['subject'], $data['category'], $data['body']);

        return $this->response($request, $case)->response()->setStatusCode(201);
    }

    public function respond(Request $request, string $complaintUuid, TenantContext $context, ManageComplaintCase $action): ComplaintMessageResource
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'min:1', 'max:20000'],
            'visibility' => ['required', 'in:internal,complainant'],
        ]);
        $message = $action->respond($this->find($context, $complaintUuid), $this->actor($request), $data['body'], $data['visibility']);

        return ComplaintMessageResource::make($message->load('author'))
            ->additional(['meta' => ['request_id' => ApiResponse::requestId($request)]]);
    }

    public function assign(Request $request, string $complaintUuid, TenantContext $context, ManageComplaintCase $action): ComplaintCaseResource
    {
        $data = $request->validate(['assignee_uuid' => ['required', 'uuid']]);
        $assignee = User::query()->where('public_id', $data['assignee_uuid'])->firstOrFail();

        return $this->response($request, $action->assign($this->find($context, $complaintUuid), $this->actor($request), $assignee)
            ->load(['member', 'reportedBy', 'assignedTo']));
    }

    public function resolve(Request $request, string $complaintUuid, TenantContext $context, ManageComplaintCase $action): ComplaintCaseResource
    {
        $data = $request->validate(['resolution_summary' => ['required', 'string', 'min:3', 'max:20000']]);

        return $this->response($request, $action->resolve($this->find($context, $complaintUuid), $this->actor($request), $data['resolution_summary'])
            ->load(['member', 'reportedBy', 'assignedTo']));
    }

    public function close(Request $request, string $complaintUuid, TenantContext $context, ManageComplaintCase $action): ComplaintCaseResource
    {
        return $this->response($request, $action->close($this->find($context, $complaintUuid), $this->actor($request))
            ->load(['member', 'reportedBy', 'assignedTo']));
    }

    public function reopen(Request $request, string $complaintUuid, TenantContext $context, ManageComplaintCase $action): ComplaintCaseResource
    {
        return $this->response($request, $action->reopen($this->find($context, $complaintUuid), $this->actor($request))
            ->load(['member', 'reportedBy', 'assignedTo']));
    }

    /** @return Builder<ComplaintCase> */
    private function scopedQuery(TenantContext $context): Builder
    {
        $query = ComplaintCase::query()->where('organisation_id', $context->organisation()->getKey());

        if ($context->branch() !== null) {
            $query->where('branch_id', $context->branch()->getKey());
        }

        return $query;
    }

    private function find(TenantContext $context, string $uuid): ComplaintCase
    {
        return $this->scopedQuery($context)->where('public_id', $uuid)->firstOrFail();
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);

        return $actor;
    }

    private function response(Request $request, ComplaintCase $case): ComplaintCaseResource
    {
        return ComplaintCaseResource::make($case)
            ->additional(['meta' => ['request_id' => ApiResponse::requestId($request)]]);
    }
}
