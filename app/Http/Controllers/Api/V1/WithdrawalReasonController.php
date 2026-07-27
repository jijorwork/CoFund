<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Domain\Withdrawals\Models\WithdrawalProduct;
use App\Domain\Withdrawals\Models\WithdrawalReason;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

class WithdrawalReasonController extends Controller
{
    public function index(Request $request, TenantContext $context, RoleAuthorization $authorization): AnonymousResourceCollection
    {
        $authorization->authorize(FoundationPermission::WithdrawalReasonsView->value);

        return JsonResource::collection(WithdrawalReason::query()->where('organisation_id', $context->organisation()->getKey())->when($request->input('filter.category'), fn ($query, $value) => $query->where('category', $value))->when($request->input('filter.status'), fn ($query, $value) => $query->where('status', $value))->latest()->paginate(min($request->integer('per_page', 25), 100)));
    }

    public function store(Request $request, TenantContext $context, RoleAuthorization $authorization): JsonResource
    {
        $authorization->authorize(FoundationPermission::WithdrawalReasonsManage->value);
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        $data = $this->data($request);
        $productUuid = $data['product_uuid'] ?? null;
        unset($data['product_uuid']);

        return new JsonResource(WithdrawalReason::query()->create([...$data, 'organisation_id' => $context->organisation()->getKey(), 'withdrawal_product_id' => $productUuid === null ? null : WithdrawalProduct::query()->where('organisation_id', $context->organisation()->getKey())->where('public_id', $productUuid)->value('id'), 'created_by_user_id' => $actor->getKey()]));
    }

    public function show(string $reasonUuid, TenantContext $context, RoleAuthorization $authorization): JsonResource
    {
        $authorization->authorize(FoundationPermission::WithdrawalReasonsView->value);

        return new JsonResource($this->find($context, $reasonUuid));
    }

    public function update(Request $request, string $reasonUuid, TenantContext $context, RoleAuthorization $authorization): JsonResource
    {
        $authorization->authorize(FoundationPermission::WithdrawalReasonsManage->value);
        $reason = $this->find($context, $reasonUuid);
        $reason->update($this->data($request));

        return new JsonResource($reason->refresh());
    }

    /** @return array<string, mixed> */
    private function data(Request $request): array
    {
        return $request->validate(['product_uuid' => ['nullable', 'uuid'], 'code' => ['required', 'string', 'max:50'], 'name' => ['required', 'string'], 'description' => ['nullable', 'string'], 'category' => ['required', 'string', 'max:40'], 'require_explanation' => ['boolean'], 'require_documentation' => ['boolean'], 'require_manager_review' => ['boolean'], 'maximum_withdrawal_minor' => ['nullable', 'integer', 'min:1'], 'maximum_withdrawal_percentage_basis_points' => ['nullable', 'integer', 'between:1,10000'], 'status' => ['sometimes', 'in:active,suspended,archived']]);
    }

    private function find(TenantContext $context, string $uuid): WithdrawalReason
    {
        return WithdrawalReason::query()->where('organisation_id', $context->organisation()->getKey())->where('public_id', $uuid)->firstOrFail();
    }
}
