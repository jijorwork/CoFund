<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Accounting\Actions\ActivateLedgerAccount;
use App\Domain\Accounting\Actions\CloseLedgerAccount;
use App\Domain\Accounting\Actions\ReinstateLedgerAccount;
use App\Domain\Accounting\Actions\SuspendLedgerAccount;
use App\Domain\Accounting\Models\LedgerAccount;
use App\Domain\Accounting\Queries\FindLedgerAccountByUuid;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LedgerAccountLifecycleRequest;
use App\Http\Resources\Api\V1\LedgerAccountResource;
use App\Http\Responses\ApiResponse;

class LedgerAccountLifecycleController extends Controller
{
    public function activate(LedgerAccountLifecycleRequest $request, string $ledgerAccountUuid, FindLedgerAccountByUuid $accounts, ActivateLedgerAccount $action): LedgerAccountResource
    {
        return $this->respond($request, $action->handle($accounts->handle($ledgerAccountUuid) ?? abort(404)));
    }

    public function suspend(LedgerAccountLifecycleRequest $request, string $ledgerAccountUuid, FindLedgerAccountByUuid $accounts, SuspendLedgerAccount $action): LedgerAccountResource
    {
        return $this->respond($request, $action->handle($accounts->handle($ledgerAccountUuid) ?? abort(404)));
    }

    public function reinstate(LedgerAccountLifecycleRequest $request, string $ledgerAccountUuid, FindLedgerAccountByUuid $accounts, ReinstateLedgerAccount $action): LedgerAccountResource
    {
        return $this->respond($request, $action->handle($accounts->handle($ledgerAccountUuid) ?? abort(404)));
    }

    public function close(LedgerAccountLifecycleRequest $request, string $ledgerAccountUuid, FindLedgerAccountByUuid $accounts, CloseLedgerAccount $action): LedgerAccountResource
    {
        return $this->respond($request, $action->handle($accounts->handle($ledgerAccountUuid) ?? abort(404)));
    }

    private function respond(LedgerAccountLifecycleRequest $request, LedgerAccount $account): LedgerAccountResource
    {
        return LedgerAccountResource::make($account)
            ->additional(['meta' => ['request_id' => ApiResponse::requestId($request)]]);
    }
}
