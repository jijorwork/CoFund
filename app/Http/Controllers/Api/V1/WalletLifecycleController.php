<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Wallets\Actions\CloseWallet;
use App\Domain\Wallets\Actions\ReinstateWallet;
use App\Domain\Wallets\Actions\SuspendWallet;
use App\Domain\Wallets\Models\Wallet;
use App\Domain\Wallets\Queries\FindWalletByUuid;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\WalletLifecycleRequest;
use App\Http\Resources\Api\V1\WalletResource;
use App\Http\Responses\ApiResponse;

class WalletLifecycleController extends Controller
{
    public function suspend(WalletLifecycleRequest $request, string $walletUuid, FindWalletByUuid $wallets, SuspendWallet $action): WalletResource
    {
        return $this->respond($request, $action->handle($wallets->handle($walletUuid) ?? abort(404)));
    }

    public function reinstate(WalletLifecycleRequest $request, string $walletUuid, FindWalletByUuid $wallets, ReinstateWallet $action): WalletResource
    {
        return $this->respond($request, $action->handle($wallets->handle($walletUuid) ?? abort(404)));
    }

    public function close(WalletLifecycleRequest $request, string $walletUuid, FindWalletByUuid $wallets, CloseWallet $action): WalletResource
    {
        return $this->respond($request, $action->handle($wallets->handle($walletUuid) ?? abort(404)));
    }

    private function respond(WalletLifecycleRequest $request, Wallet $wallet): WalletResource
    {
        return WalletResource::make($wallet)
            ->additional(['meta' => ['request_id' => ApiResponse::requestId($request)]]);
    }
}
