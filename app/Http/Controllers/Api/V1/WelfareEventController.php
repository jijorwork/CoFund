<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Membership\Models\Member;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Domain\Welfare\Actions\CreateWelfareEvent;
use App\Domain\Welfare\Actions\ManageWelfareEvent;
use App\Domain\Welfare\Models\WelfareEvent;
use App\Domain\Welfare\Models\WelfareEventType;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

class WelfareEventController extends Controller
{
    public function index(Request $request, TenantContext $context, RoleAuthorization $authorization): AnonymousResourceCollection
    {
        $authorization->authorize(FoundationPermission::WelfareClaimsView->value);
        $query = WelfareEvent::query()->where('organisation_id', $context->organisation()->getKey())->with(['member', 'eventType'])->when($context->branch(), fn ($query, $branch) => $query->where('branch_id', $branch->getKey()))->when($request->input('filter.status'), fn ($query, $value) => $query->where('status', $value));

        return JsonResource::collection($query->latest()->paginate(min($request->integer('per_page', 25), 100)))->additional(['meta' => ['request_id' => ApiResponse::requestId($request)]]);
    }

    public function store(Request $request, TenantContext $context, CreateWelfareEvent $action): JsonResource
    {
        $data = $this->data($request);
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        $member = Member::query()->whereBelongsTo($context->organisation())->where('public_id', $data['member_uuid'])->firstOrFail();
        $type = WelfareEventType::query()->where('organisation_id', $context->organisation()->getKey())->where('public_id', $data['event_type_uuid'])->firstOrFail();
        unset($data['member_uuid'], $data['event_type_uuid']);

        return new JsonResource($action->handle($actor, $member, $type, $data));
    }

    public function show(string $eventUuid, TenantContext $context, RoleAuthorization $authorization): JsonResource
    {
        $authorization->authorize(FoundationPermission::WelfareClaimsView->value);

        return new JsonResource($this->find($context, $eventUuid)->load(['member', 'eventType']));
    }

    public function update(Request $request, string $eventUuid, TenantContext $context, ManageWelfareEvent $action): JsonResource
    {
        $data = $this->data($request, false);
        unset($data['member_uuid'], $data['event_type_uuid']);

        return new JsonResource($action->update($this->find($context, $eventUuid), $data));
    }

    public function transition(Request $request, string $eventUuid, string $transition, TenantContext $context, ManageWelfareEvent $action): JsonResource
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        $event = $this->find($context, $eventUuid);
        $reason = $request->string('reason')->toString();
        $result = match ($transition) {
            'verify' => $action->verify($actor, $event, $reason),
            'reject' => $action->reject($actor, $event, $reason),
            'cancel' => $action->cancel($event, $reason),
            default => throw new \InvalidArgumentException('Unsupported welfare event transition.'),
        };

        return new JsonResource($result);
    }

    /** @return array<string,mixed> */
    private function data(Request $request, bool $required = true): array
    {
        $presence = $required ? 'required' : 'sometimes';

        return $request->validate(['member_uuid' => [$presence, 'uuid'], 'event_type_uuid' => [$presence, 'uuid'], 'event_date' => [$presence, 'date'], 'reported_date' => [$presence, 'date'], 'title' => [$presence, 'string'], 'description' => ['nullable', 'string'], 'location' => ['nullable', 'string'], 'related_person_name' => ['nullable', 'string'], 'related_person_type' => ['nullable', 'string'], 'relationship_to_member' => ['nullable', 'string']]);
    }

    private function find(TenantContext $context, string $uuid): WelfareEvent
    {
        return WelfareEvent::query()->where('organisation_id', $context->organisation()->getKey())->when($context->branch(), fn ($query, $branch) => $query->where('branch_id', $branch->getKey()))->where('public_id', $uuid)->firstOrFail();
    }
}
