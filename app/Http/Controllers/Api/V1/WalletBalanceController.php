<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Wallets\Queries\FindWalletByUuid;
use App\Domain\Wallets\Services\CalculateWalletBalance;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\WalletBalanceRequest;
use App\Http\Responses\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;

class WalletBalanceController extends Controller
{
    public function __invoke(
        WalletBalanceRequest $request,
        string $walletUuid,
        FindWalletByUuid $wallets,
        CalculateWalletBalance $balances,
    ): JsonResponse {
        $wallet = $wallets->handle($walletUuid) ?? abort(404);
        $asOf = $request->filled('as_of')
            ? CarbonImmutable::parse($request->validated('as_of'))
            : null;

        return ApiResponse::success($request, [
            'wallet_uuid' => $wallet->public_id,
            'currency_code' => $wallet->currency_code,
            'as_of' => $asOf?->toDateString(),
            ...$balances->calculate($wallet, $asOf)->toArray(),
        ]);
    }
}
