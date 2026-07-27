<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Domain\Reconciliation\Actions\ManageCashDeposit;
use App\Domain\Reconciliation\Enums\CashDepositMethod;
use App\Domain\Reconciliation\Models\CashDepositBatch;
use App\Domain\Wallets\Models\Wallet;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CashDepositResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CashDepositController extends Controller
{
    public function index(Request $request, TenantContext $context, RoleAuthorization $authorization): AnonymousResourceCollection
    {
        $authorization->authorize(FoundationPermission::CashDepositsView->value);

        return CashDepositResource::collection(CashDepositBatch::query()->whereBelongsTo($context->organisation())
            ->when($context->roleAssignment()->branch_id !== null, fn ($query) => $query->where('branch_id', $context->branch()?->getKey()))
            ->with($this->relations())->latest('deposit_date')->paginate(min($request->integer('per_page', 20), 100)))
            ->additional(['meta' => ['request_id' => ApiResponse::requestId($request)]]);
    }

    public function store(Request $request, TenantContext $context, ManageCashDeposit $action): CashDepositResource
    {
        $data = $request->validate([
            'source_wallet_uuid' => ['required', 'uuid'], 'destination_wallet_uuid' => ['required', 'uuid'],
            'deposit_method' => ['required', 'string'], 'deposit_date' => ['required', 'date'],
            'amount_minor' => ['required', 'integer', 'min:1'], 'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $key = (string) $request->header('Idempotency-Key', '');
        abort_if($key === '', 422, 'Idempotency-Key is required.');
        $source = Wallet::query()->whereBelongsTo($context->organisation())->where('public_id', $data['source_wallet_uuid'])->firstOrFail();
        $destination = Wallet::query()->whereBelongsTo($context->organisation())->where('public_id', $data['destination_wallet_uuid'])->firstOrFail();
        $deposit = $action->create($this->actor($request), $source, $destination, CashDepositMethod::from($data['deposit_method']),
            CarbonImmutable::parse($data['deposit_date']), $data['amount_minor'], $key, $data['reference'] ?? null, $data['notes'] ?? null);

        return $this->resource($request, $deposit);
    }

    public function show(Request $request, string $depositUuid, TenantContext $context, RoleAuthorization $authorization): CashDepositResource
    {
        $authorization->authorize(FoundationPermission::CashDepositsView->value);

        return $this->resource($request, $this->find($context, $depositUuid));
    }

    public function submit(Request $request, string $depositUuid, TenantContext $context, ManageCashDeposit $action): CashDepositResource
    {
        return $this->resource($request, $action->submit($this->actor($request), $this->find($context, $depositUuid)));
    }

    public function confirm(Request $request, string $depositUuid, TenantContext $context, ManageCashDeposit $action): CashDepositResource
    {
        return $this->resource($request, $action->confirm($this->actor($request), $this->find($context, $depositUuid)));
    }

    public function reject(Request $request, string $depositUuid, TenantContext $context, ManageCashDeposit $action): CashDepositResource
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);

        return $this->resource($request, $action->reject($this->actor($request), $this->find($context, $depositUuid), $data['reason']));
    }

    public function reverse(Request $request, string $depositUuid, TenantContext $context, ManageCashDeposit $action): CashDepositResource
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        $key = (string) $request->header('Idempotency-Key', '');
        abort_if($key === '', 422, 'Idempotency-Key is required.');

        return $this->resource($request, $action->reverse($this->actor($request), $this->find($context, $depositUuid), $data['reason'], $key));
    }

    private function find(TenantContext $context, string $uuid): CashDepositBatch
    {
        return CashDepositBatch::query()->whereBelongsTo($context->organisation())->where('public_id', $uuid)
            ->when($context->roleAssignment()->branch_id !== null, fn ($query) => $query->where('branch_id', $context->branch()?->getKey()))
            ->with($this->relations())->firstOrFail();
    }

    /** @return list<string> */
    private function relations(): array
    {
        return ['sourceWallet', 'destinationWallet', 'financialTransaction'];
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);

        return $actor;
    }

    private function resource(Request $request, CashDepositBatch $model): CashDepositResource
    {
        return CashDepositResource::make($model->loadMissing($this->relations()))->additional(['meta' => ['request_id' => ApiResponse::requestId($request)]]);
    }
}
