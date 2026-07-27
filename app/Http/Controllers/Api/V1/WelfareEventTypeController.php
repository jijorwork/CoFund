<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Domain\Welfare\Models\WelfareClaim;
use App\Domain\Welfare\Models\WelfareEventType;
use App\Domain\Welfare\Models\WelfareProduct;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

class WelfareEventTypeController extends Controller
{
    public function index(Request $request, TenantContext $context, RoleAuthorization $authorization): AnonymousResourceCollection
    {
        $authorization->authorize(FoundationPermission::WelfareEventTypesView->value);
        $query = WelfareEventType::query()->where('organisation_id', $context->organisation()->getKey())->when($request->input('filter.event_category'), fn ($query, $value) => $query->where('event_category', $value))->when($request->input('filter.status'), fn ($query, $value) => $query->where('status', $value));

        return JsonResource::collection($query->latest()->paginate(min($request->integer('per_page', 25), 100)))->additional(['meta' => ['request_id' => ApiResponse::requestId($request)]]);
    }

    public function store(Request $request, TenantContext $context, RoleAuthorization $authorization): JsonResource
    {
        $authorization->authorize(FoundationPermission::WelfareEventTypesManage->value);
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        $data = $this->validateData($request);
        $productUuid = $data['welfare_product_uuid'] ?? null;
        unset($data['welfare_product_uuid']);
        $data['welfare_product_id'] = $productUuid === null ? null : WelfareProduct::query()->where('organisation_id', $context->organisation()->getKey())->where('public_id', $productUuid)->value('id');

        return new JsonResource(WelfareEventType::query()->create([...$data, 'organisation_id' => $context->organisation()->getKey(), 'created_by_user_id' => $actor->getKey()]));
    }

    public function show(string $eventTypeUuid, TenantContext $context, RoleAuthorization $authorization): JsonResource
    {
        $authorization->authorize(FoundationPermission::WelfareEventTypesView->value);

        return new JsonResource($this->find($context, $eventTypeUuid));
    }

    public function update(Request $request, string $eventTypeUuid, TenantContext $context, RoleAuthorization $authorization): JsonResource
    {
        $authorization->authorize(FoundationPermission::WelfareEventTypesManage->value);
        $eventType = $this->find($context, $eventTypeUuid);
        if (WelfareClaim::query()->where('welfare_event_type_id', $eventType->getKey())->exists()) {
            abort(422, 'Historical event types are immutable.');
        } $eventType->update($this->validateData($request));

        return new JsonResource($eventType->refresh());
    }

    /** @return array<string,mixed> */
    private function validateData(Request $request): array
    {
        return $request->validate(['welfare_product_uuid' => ['nullable', 'uuid'], 'code' => ['required', 'string', 'max:50'], 'name' => ['required', 'string'], 'description' => ['nullable', 'string'], 'event_category' => ['required', 'string', 'max:30'], 'default_benefit_minor' => ['nullable', 'integer', 'min:0'], 'minimum_benefit_minor' => ['nullable', 'integer', 'min:0'], 'maximum_benefit_minor' => ['nullable', 'integer', 'gte:minimum_benefit_minor'], 'require_event_date' => ['boolean'], 'require_location' => ['boolean'], 'require_related_person' => ['boolean'], 'require_relationship' => ['boolean'], 'require_documentation' => ['boolean'], 'require_verification' => ['boolean'], 'claim_submission_deadline_days' => ['nullable', 'integer', 'min:1'], 'maximum_occurrences_per_member' => ['nullable', 'integer', 'min:1'], 'maximum_occurrences_period' => ['nullable', 'in:month,year,lifetime'], 'status' => ['sometimes', 'in:active,suspended,archived']]);
    }

    private function find(TenantContext $context, string $uuid): WelfareEventType
    {
        return WelfareEventType::query()->where('organisation_id', $context->organisation()->getKey())->where('public_id', $uuid)->firstOrFail();
    }
}
