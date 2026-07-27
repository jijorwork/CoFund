<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Membership\Models\Member;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Domain\Wallets\Models\Wallet;
use App\Domain\Withdrawals\Actions\ManageSavingsHold;
use App\Domain\Withdrawals\Models\SavingsHold;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

class SavingsHoldController extends Controller
{
    public function index(Request $request, TenantContext $context, RoleAuthorization $authorization): AnonymousResourceCollection
    {
        $authorization->authorize(FoundationPermission::SavingsHoldsView->value);

        return JsonResource::collection(SavingsHold::query()->where('organisation_id', $context->organisation()->getKey())->with(['member', 'wallet'])->when($context->branch(), fn ($query, $branch) => $query->where('branch_id', $branch->getKey()))->when($request->input('filter.status'), fn ($query, $value) => $query->where('status', $value))->latest()->paginate(min($request->integer('per_page', 25), 100)));
    }

    public function store(Request $request, TenantContext $context, ManageSavingsHold $action): JsonResource
    {
        $data = $request->validate(['member_uuid' => ['required', 'uuid'], 'wallet_uuid' => ['required', 'uuid'], 'hold_type' => ['required', 'string', 'max:40'], 'amount_minor' => ['required', 'integer', 'min:1'], 'reason' => ['required', 'string'], 'source_type' => ['nullable', 'string'], 'source_uuid' => ['nullable', 'uuid']]);
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        $member = Member::query()->where('organisation_id', $context->organisation()->getKey())->where('public_id', $data['member_uuid'])->firstOrFail();
        $wallet = Wallet::query()->where('organisation_id', $context->organisation()->getKey())->where('public_id', $data['wallet_uuid'])->firstOrFail();

        return new JsonResource($action->create($actor, $member, $wallet, $data['hold_type'], $data['amount_minor'], $data['reason'], $data['source_type'] ?? 'manual', $data['source_uuid'] ?? null));
    }

    public function show(string $holdUuid, TenantContext $context, RoleAuthorization $authorization): JsonResource
    {
        $authorization->authorize(FoundationPermission::SavingsHoldsView->value);

        return new JsonResource($this->find($context, $holdUuid)->load(['member', 'wallet']));
    }

    public function release(Request $request, string $holdUuid, TenantContext $context, ManageSavingsHold $action): JsonResource
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);

        return new JsonResource($action->release($actor, $this->find($context, $holdUuid), $request->string('reason')->toString()));
    }

    public function transition(Request $request, string $holdUuid, string $transition, TenantContext $context, ManageSavingsHold $action): JsonResource
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        $hold = $this->find($context, $holdUuid);
        $result = match ($transition) {
            'approve' => $action->approve($actor, $hold),
            'cancel' => $action->cancel($actor, $hold, $request->string('reason')->toString()),
            default => throw new \InvalidArgumentException('Unsupported savings hold transition.'),
        };

        return new JsonResource($result);
    }

    private function find(TenantContext $context, string $uuid): SavingsHold
    {
        return SavingsHold::query()->where('organisation_id', $context->organisation()->getKey())->where('public_id', $uuid)->firstOrFail();
    }
}
