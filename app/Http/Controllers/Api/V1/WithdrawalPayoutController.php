<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Domain\Wallets\Models\Wallet;
use App\Domain\Withdrawals\Actions\PayApprovedWithdrawal;
use App\Domain\Withdrawals\Actions\ReverseWithdrawalPayout;
use App\Domain\Withdrawals\Models\WithdrawalPayout;
use App\Domain\Withdrawals\Models\WithdrawalRequest;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\WithdrawalRequestForm;
use App\Http\Resources\Api\V1\WithdrawalPayoutResource;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WithdrawalPayoutController extends Controller
{
    public function index(Request $request, TenantContext $context, RoleAuthorization $authorization): AnonymousResourceCollection
    {
        $authorization->authorize(FoundationPermission::WithdrawalPayoutsView->value);

        return WithdrawalPayoutResource::collection(WithdrawalPayout::query()->where('organisation_id', $context->organisation()->getKey())->with(['request', 'destinationWallet', 'financialTransaction', 'receipt'])->when($context->branch(), fn ($query, $branch) => $query->where('branch_id', $branch->getKey()))->latest()->paginate(min($request->integer('per_page', 25), 100)));
    }

    public function store(WithdrawalRequestForm $request, string $requestUuid, TenantContext $context, PayApprovedWithdrawal $action): WithdrawalPayoutResource
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        $withdrawal = WithdrawalRequest::query()->where('organisation_id', $context->organisation()->getKey())->where('public_id', $requestUuid)->firstOrFail();
        $destination = Wallet::query()->where('organisation_id', $context->organisation()->getKey())->where('public_id', $request->validated('destination_wallet_uuid'))->firstOrFail();

        return WithdrawalPayoutResource::make($action->handle($actor, $withdrawal, $destination, $request->integer('amount_minor'), $request->validated('payment_method'), CarbonImmutable::parse($request->validated('payout_date')), $request->idempotencyKey(), $request->validated('recipient_type'), $request->validated('recipient_name')));
    }

    public function show(string $payoutUuid, TenantContext $context, RoleAuthorization $authorization): WithdrawalPayoutResource
    {
        $authorization->authorize(FoundationPermission::WithdrawalPayoutsView->value);

        return WithdrawalPayoutResource::make($this->find($context, $payoutUuid)->load(['request', 'destinationWallet', 'financialTransaction', 'receipt']));
    }

    public function reverse(WithdrawalRequestForm $request, string $payoutUuid, TenantContext $context, ReverseWithdrawalPayout $action): WithdrawalPayoutResource
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);

        return WithdrawalPayoutResource::make($action->handle($actor, $this->find($context, $payoutUuid), (string) $request->validated('reason'), $request->idempotencyKey())->load(['request', 'destinationWallet', 'financialTransaction', 'receipt']));
    }

    private function find(TenantContext $context, string $uuid): WithdrawalPayout
    {
        return WithdrawalPayout::query()->where('organisation_id', $context->organisation()->getKey())->where('public_id', $uuid)->firstOrFail();
    }
}
