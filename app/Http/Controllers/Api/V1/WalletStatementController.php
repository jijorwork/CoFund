<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Wallets\Queries\FindWalletByUuid;
use App\Domain\Wallets\Services\BuildWalletStatement;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\WalletStatementRequest;
use App\Http\Responses\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;

class WalletStatementController extends Controller
{
    public function __invoke(
        WalletStatementRequest $request,
        string $walletUuid,
        FindWalletByUuid $wallets,
        BuildWalletStatement $statements,
    ): JsonResponse {
        $wallet = $wallets->handle($walletUuid) ?? abort(404);

        return ApiResponse::success($request, $statements->build(
            $wallet,
            CarbonImmutable::parse($request->validated('from')),
            CarbonImmutable::parse($request->validated('to')),
            (int) $request->validated('page', 1),
            (int) $request->validated('per_page', 50),
        ));
    }
}
