<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Membership\Models\Member;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Domain\Wallets\Models\Wallet;
use App\Domain\Withdrawals\Models\WithdrawalProduct;
use App\Domain\Withdrawals\Models\WithdrawalReason;
use App\Domain\Withdrawals\Services\CalculateWithdrawableSavings;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WithdrawableSavingsController extends Controller
{
    public function __invoke(Request $request, string $memberUuid, TenantContext $context, RoleAuthorization $authorization, CalculateWithdrawableSavings $service): JsonResource
    {
        $authorization->authorize(FoundationPermission::WithdrawalRequestsView->value);
        $data = $request->validate(['withdrawal_product_uuid' => ['required', 'uuid'], 'withdrawal_reason_uuid' => ['nullable', 'uuid'], 'savings_wallet_uuid' => ['required', 'uuid']]);
        $member = Member::query()->where('organisation_id', $context->organisation()->getKey())->where('public_id', $memberUuid)->firstOrFail();
        $product = WithdrawalProduct::query()->where('organisation_id', $context->organisation()->getKey())->where('public_id', $data['withdrawal_product_uuid'])->firstOrFail();
        $wallet = Wallet::query()->where('organisation_id', $context->organisation()->getKey())->where('public_id', $data['savings_wallet_uuid'])->firstOrFail();
        $reason = empty($data['withdrawal_reason_uuid']) ? null : WithdrawalReason::query()->where('organisation_id', $context->organisation()->getKey())->where('public_id', $data['withdrawal_reason_uuid'])->firstOrFail();

        return new JsonResource($service->calculate($member, $product, $wallet, $reason));
    }
}
