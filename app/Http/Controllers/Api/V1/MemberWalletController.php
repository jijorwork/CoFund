<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Membership\Queries\FindMemberByUuid;
use App\Domain\Wallets\Services\CalculateMemberWalletBalances;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MemberWalletController extends Controller
{
    public function __invoke(
        Request $request,
        string $memberUuid,
        FindMemberByUuid $members,
        CalculateMemberWalletBalances $balances,
        RoleAuthorization $authorization,
    ): JsonResponse {
        $authorization->authorize(FoundationPermission::WalletsView->value);
        $member = $members->handle($memberUuid) ?? abort(404);
        $asOf = CarbonImmutable::today();
        $wallets = collect($balances->calculate($member, $asOf))->map(
            fn (array $item): array => [
                'uuid' => $item['wallet']->public_id,
                'wallet_type' => $item['wallet']->wallet_type->value,
                'name' => $item['wallet']->name,
                'currency_code' => $item['wallet']->currency_code,
                'status' => $item['wallet']->status->value,
                'balance' => $item['balance']->toArray(),
                'as_of' => $asOf->toDateString(),
            ],
        )->all();

        return ApiResponse::success($request, ['wallets' => $wallets]);
    }
}
