<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Domain\Reconciliation\Actions\ManageCollectorCashHandover;
use App\Domain\Reconciliation\Models\CollectorCashHandover;
use App\Domain\Reconciliation\Models\CollectorCollectionSession;
use App\Domain\Wallets\Models\Wallet;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CashHandoverResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CashHandoverController extends Controller
{
    public function index(Request $request, TenantContext $context, RoleAuthorization $authorization): AnonymousResourceCollection
    {
        $authorization->authorize(FoundationPermission::CashHandoversView->value);

        return CashHandoverResource::collection(CollectorCashHandover::query()->whereBelongsTo($context->organisation())
            ->when($context->roleAssignment()->branch_id !== null, fn ($query) => $query->where('branch_id', $context->branch()?->getKey()))
            ->with($this->relations())->latest()->paginate(min($request->integer('per_page', 20), 100)))
            ->additional(['meta' => ['request_id' => ApiResponse::requestId($request)]]);
    }

    public function store(Request $request, TenantContext $context, ManageCollectorCashHandover $action): CashHandoverResource
    {
        $data = $request->validate(['session_uuid' => ['required', 'uuid'], 'destination_wallet_uuid' => ['required', 'uuid']]);
        $key = (string) $request->header('Idempotency-Key', '');
        abort_if($key === '', 422, 'Idempotency-Key is required.');
        $session = CollectorCollectionSession::query()->whereBelongsTo($context->organisation())->where('public_id', $data['session_uuid'])->firstOrFail();
        $wallet = Wallet::query()->whereBelongsTo($context->organisation())->where('public_id', $data['destination_wallet_uuid'])->firstOrFail();

        return $this->resource($request, $action->create($this->actor($request), $session, $wallet, $key));
    }

    public function show(Request $request, string $handoverUuid, TenantContext $context, RoleAuthorization $authorization): CashHandoverResource
    {
        $authorization->authorize(FoundationPermission::CashHandoversView->value);

        return $this->resource($request, $this->find($context, $handoverUuid));
    }

    public function submit(Request $request, string $handoverUuid, TenantContext $context, ManageCollectorCashHandover $action): CashHandoverResource
    {
        return $this->resource($request, $action->submit($this->actor($request), $this->find($context, $handoverUuid)));
    }

    public function receive(Request $request, string $handoverUuid, TenantContext $context, ManageCollectorCashHandover $action): CashHandoverResource
    {
        $data = $request->validate(['accepted_cash_minor' => ['required', 'integer', 'min:1'], 'denomination_breakdown' => ['sometimes', 'array'], 'denomination_breakdown.*.denomination_minor' => ['required', 'integer'], 'denomination_breakdown.*.quantity' => ['required', 'integer']]);

        return $this->resource($request, $action->receive($this->actor($request), $this->find($context, $handoverUuid), $data['accepted_cash_minor'], $data['denomination_breakdown'] ?? []));
    }

    public function approve(Request $request, string $handoverUuid, TenantContext $context, ManageCollectorCashHandover $action): CashHandoverResource
    {
        return $this->resource($request, $action->approve($this->actor($request), $this->find($context, $handoverUuid)));
    }

    public function reject(Request $request, string $handoverUuid, TenantContext $context, ManageCollectorCashHandover $action): CashHandoverResource
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);

        return $this->resource($request, $action->reject($this->actor($request), $this->find($context, $handoverUuid), $data['reason']));
    }

    public function reverse(Request $request, string $handoverUuid, TenantContext $context, ManageCollectorCashHandover $action): CashHandoverResource
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        $key = (string) $request->header('Idempotency-Key', '');
        abort_if($key === '', 422, 'Idempotency-Key is required.');

        return $this->resource($request, $action->reverse($this->actor($request), $this->find($context, $handoverUuid), $data['reason'], $key));
    }

    private function find(TenantContext $context, string $uuid): CollectorCashHandover
    {
        return CollectorCashHandover::query()->whereBelongsTo($context->organisation())->where('public_id', $uuid)
            ->when($context->roleAssignment()->branch_id !== null, fn ($query) => $query->where('branch_id', $context->branch()?->getKey()))
            ->with($this->relations())->firstOrFail();
    }

    /** @return list<string> */
    private function relations(): array
    {
        return ['session', 'collectorWallet', 'destinationWallet', 'financialTransaction'];
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);

        return $actor;
    }

    private function resource(Request $request, CollectorCashHandover $model): CashHandoverResource
    {
        return CashHandoverResource::make($model->loadMissing($this->relations()))->additional(['meta' => ['request_id' => ApiResponse::requestId($request)]]);
    }
}
