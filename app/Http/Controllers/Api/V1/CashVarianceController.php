<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Domain\Reconciliation\Actions\WriteOffCashVariance;
use App\Domain\Reconciliation\Enums\CashVarianceResolutionType;
use App\Domain\Reconciliation\Enums\CashVarianceStatus;
use App\Domain\Reconciliation\Exceptions\InvalidReconciliationOperation;
use App\Domain\Reconciliation\Models\CollectorCashVariance;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CashVarianceResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CashVarianceController extends Controller
{
    public function index(Request $request, TenantContext $context, RoleAuthorization $authorization): AnonymousResourceCollection
    {
        $authorization->authorize(FoundationPermission::CashVariancesView->value);

        return CashVarianceResource::collection(CollectorCashVariance::query()->whereBelongsTo($context->organisation())
            ->when($context->roleAssignment()->branch_id !== null, fn ($query) => $query->where('branch_id', $context->branch()?->getKey()))
            ->latest('reported_at')->paginate(min($request->integer('per_page', 20), 100)))
            ->additional(['meta' => ['request_id' => ApiResponse::requestId($request)]]);
    }

    public function show(Request $request, string $varianceUuid, TenantContext $context, RoleAuthorization $authorization): CashVarianceResource
    {
        $authorization->authorize(FoundationPermission::CashVariancesView->value);

        return $this->resource($request, $this->find($context, $varianceUuid));
    }

    public function resolve(Request $request, string $varianceUuid, TenantContext $context, RoleAuthorization $authorization): CashVarianceResource
    {
        $authorization->authorize(FoundationPermission::CashVariancesResolve->value);
        $data = $request->validate(['resolution_type' => ['required', 'string'], 'notes' => ['required', 'string', 'max:2000']]);
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        $variance = $this->find($context, $varianceUuid);
        if ($variance->status !== CashVarianceStatus::Open || $variance->reported_by_user_id === $actor->getKey()) {
            throw new InvalidReconciliationOperation('Variance resolution requires independent review.');
        }
        $variance->update(['status' => CashVarianceStatus::Resolved, 'resolution_type' => CashVarianceResolutionType::from($data['resolution_type']), 'resolution_notes' => $data['notes'], 'resolved_at' => now(), 'resolved_by_user_id' => $actor->getKey()]);

        return $this->resource($request, $variance->refresh());
    }

    public function writeOff(Request $request, string $varianceUuid, TenantContext $context, WriteOffCashVariance $action): CashVarianceResource
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        $key = (string) $request->header('Idempotency-Key', '');
        abort_if($key === '', 422, 'Idempotency-Key is required.');
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);

        return $this->resource($request, $action->handle($actor, $this->find($context, $varianceUuid), $data['reason'], $key));
    }

    private function find(TenantContext $context, string $uuid): CollectorCashVariance
    {
        return CollectorCashVariance::query()->whereBelongsTo($context->organisation())->where('public_id', $uuid)
            ->when($context->roleAssignment()->branch_id !== null, fn ($query) => $query->where('branch_id', $context->branch()?->getKey()))
            ->firstOrFail();
    }

    private function resource(Request $request, CollectorCashVariance $model): CashVarianceResource
    {
        return CashVarianceResource::make($model)->additional(['meta' => ['request_id' => ApiResponse::requestId($request)]]);
    }
}
