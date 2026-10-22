<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identity\Auditing\Models\AuditEvent;
use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\AuditEventResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AuditEventController extends Controller
{
    public function index(Request $request, TenantContext $context, RoleAuthorization $authorization): AnonymousResourceCollection
    {
        $authorization->authorize(FoundationPermission::ReportsAuditView->value);
        $filters = $request->validate([
            'event_key' => ['sometimes', 'string', 'max:120'],
            'subject_public_id' => ['sometimes', 'uuid'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $query = AuditEvent::query()->with('actor')->where('organisation_id', $context->organisation()->getKey());

        if ($context->roleAssignment()->branch_id !== null) {
            $query->where('branch_id', $context->roleAssignment()->branch_id);
        }

        foreach (['event_key', 'subject_public_id'] as $field) {
            if (isset($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }

        return AuditEventResource::collection($query->latest('id')->paginate($filters['per_page'] ?? 25)->withQueryString())
            ->additional(['meta' => ['request_id' => ApiResponse::requestId($request)]]);
    }

    public function show(Request $request, string $auditEventUuid, TenantContext $context, RoleAuthorization $authorization): AuditEventResource
    {
        $authorization->authorize(FoundationPermission::ReportsAuditView->value);
        $query = AuditEvent::query()->with('actor')->where('organisation_id', $context->organisation()->getKey());

        if ($context->roleAssignment()->branch_id !== null) {
            $query->where('branch_id', $context->roleAssignment()->branch_id);
        }

        return AuditEventResource::make($query->where('public_id', $auditEventUuid)->firstOrFail())
            ->additional(['meta' => ['request_id' => ApiResponse::requestId($request)]]);
    }
}
