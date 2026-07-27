<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Domain\Reconciliation\Actions\CloseCollectorCollectionSession;
use App\Domain\Reconciliation\Actions\DeclareCollectorCash;
use App\Domain\Reconciliation\Actions\OpenCollectorCollectionSession;
use App\Domain\Reconciliation\Actions\ReviewCollectorSession;
use App\Domain\Reconciliation\Actions\SubmitCollectorSessionForReconciliation;
use App\Domain\Reconciliation\Models\CollectorCollectionSession;
use App\Domain\Wallets\Models\Wallet;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CollectorSessionResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CollectorSessionController extends Controller
{
    public function index(Request $request, TenantContext $context, RoleAuthorization $authorization): AnonymousResourceCollection
    {
        $authorization->authorize(FoundationPermission::CollectionSessionsView->value);
        $sessions = CollectorCollectionSession::query()->whereBelongsTo($context->organisation())
            ->when($context->roleAssignment()->branch_id !== null, fn ($query) => $query->where('branch_id', $context->branch()?->getKey()))
            ->with(['collector', 'collectorWallet'])->latest('session_date')->paginate(min((int) $request->integer('per_page', 20), 100));

        return CollectorSessionResource::collection($sessions)->additional(['meta' => ['request_id' => ApiResponse::requestId($request)]]);
    }

    public function store(Request $request, TenantContext $context, OpenCollectorCollectionSession $action): CollectorSessionResource
    {
        $data = $request->validate(['collector_uuid' => ['required', 'uuid'], 'collector_wallet_uuid' => ['required', 'uuid'], 'session_date' => ['required', 'date'], 'notes' => ['nullable', 'string', 'max:2000']]);
        $actor = $this->actor($request);
        $collector = User::query()->where('public_id', $data['collector_uuid'])->firstOrFail();
        $wallet = Wallet::query()->whereBelongsTo($context->organisation())->where('public_id', $data['collector_wallet_uuid'])->firstOrFail();

        return $this->resource($request, $action->handle($actor, $collector, $wallet, CarbonImmutable::parse($data['session_date']), $data['notes'] ?? null));
    }

    public function show(Request $request, string $sessionUuid, TenantContext $context, RoleAuthorization $authorization): CollectorSessionResource
    {
        $authorization->authorize(FoundationPermission::CollectionSessionsView->value);

        return $this->resource($request, $this->find($context, $sessionUuid));
    }

    public function close(Request $request, string $sessionUuid, TenantContext $context, CloseCollectorCollectionSession $action): CollectorSessionResource
    {
        return $this->resource($request, $action->handle($this->actor($request), $this->find($context, $sessionUuid)));
    }

    public function declareCash(Request $request, string $sessionUuid, TenantContext $context, DeclareCollectorCash $action): CollectorSessionResource
    {
        $data = $request->validate(['declared_cash_minor' => ['required', 'integer', 'min:0'], 'denomination_breakdown' => ['sometimes', 'array'], 'denomination_breakdown.*.denomination_minor' => ['required', 'integer'], 'denomination_breakdown.*.quantity' => ['required', 'integer'], 'notes' => ['nullable', 'string', 'max:2000']]);
        $session = $this->find($context, $sessionUuid);
        $action->handle($this->actor($request), $session, $data['declared_cash_minor'], $data['denomination_breakdown'] ?? [], $data['notes'] ?? null);

        return $this->resource($request, $session->refresh());
    }

    public function submit(Request $request, string $sessionUuid, TenantContext $context, SubmitCollectorSessionForReconciliation $action): CollectorSessionResource
    {
        return $this->resource($request, $action->handle($this->actor($request), $this->find($context, $sessionUuid)));
    }

    public function approve(Request $request, string $sessionUuid, TenantContext $context, ReviewCollectorSession $action): CollectorSessionResource
    {
        return $this->resource($request, $action->approve($this->actor($request), $this->find($context, $sessionUuid)));
    }

    public function reject(Request $request, string $sessionUuid, TenantContext $context, ReviewCollectorSession $action): CollectorSessionResource
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);

        return $this->resource($request, $action->reject($this->actor($request), $this->find($context, $sessionUuid), $data['reason']));
    }

    private function find(TenantContext $context, string $uuid): CollectorCollectionSession
    {
        return CollectorCollectionSession::query()->whereBelongsTo($context->organisation())->where('public_id', $uuid)
            ->when($context->roleAssignment()->branch_id !== null, fn ($query) => $query->where('branch_id', $context->branch()?->getKey()))
            ->with(['collector', 'collectorWallet', 'declarations', 'handovers', 'variances'])->firstOrFail();
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);

        return $actor;
    }

    private function resource(Request $request, CollectorCollectionSession $session): CollectorSessionResource
    {
        return CollectorSessionResource::make($session->loadMissing(['collector', 'collectorWallet']))->additional(['meta' => ['request_id' => ApiResponse::requestId($request)]]);
    }
}
