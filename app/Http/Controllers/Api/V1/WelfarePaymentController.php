<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Domain\Wallets\Models\Wallet;
use App\Domain\Welfare\Actions\PayWelfareBenefit;
use App\Domain\Welfare\Actions\RecogniseApprovedWelfareClaim;
use App\Domain\Welfare\Actions\ReverseWelfareBenefitPayment;
use App\Domain\Welfare\Actions\ReverseWelfareClaimLiability;
use App\Domain\Welfare\Models\WelfareBenefitPayment;
use App\Domain\Welfare\Models\WelfareClaim;
use App\Domain\Welfare\Models\WelfareClaimBeneficiary;
use App\Domain\Welfare\Models\WelfareClaimLiability;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\WelfareFinancialRequest;
use App\Http\Resources\Api\V1\WelfarePaymentResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

class WelfarePaymentController extends Controller
{
    public function liability(string $claimUuid, TenantContext $context, RoleAuthorization $authorization): JsonResource
    {
        $authorization->authorize(FoundationPermission::WelfareLiabilitiesView->value);

        return new JsonResource($this->claim($context, $claimUuid)->liabilities()->latest()->firstOrFail());
    }

    public function recognise(WelfareFinancialRequest $request, string $claimUuid, TenantContext $context, RecogniseApprovedWelfareClaim $action): JsonResource
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);

        return new JsonResource($action->handle($actor, $this->claim($context, $claimUuid), CarbonImmutable::parse($request->validated('date')), $request->idempotencyKey()));
    }

    public function index(Request $request, TenantContext $context, RoleAuthorization $authorization): AnonymousResourceCollection
    {
        $authorization->authorize(FoundationPermission::WelfarePaymentsView->value);
        $query = WelfareBenefitPayment::query()->where('organisation_id', $context->organisation()->getKey())->with(['claim', 'beneficiary', 'sourceWallet', 'financialTransaction', 'receipt'])->when($context->branch(), fn ($query, $branch) => $query->where('branch_id', $branch->getKey()))->when($request->input('filter.status'), fn ($query, $value) => $query->where('status', $value));

        return WelfarePaymentResource::collection($query->latest()->paginate(min($request->integer('per_page', 25), 100)))->additional(['meta' => ['request_id' => ApiResponse::requestId($request)]]);
    }

    public function store(WelfareFinancialRequest $request, string $claimUuid, TenantContext $context, PayWelfareBenefit $action): WelfarePaymentResource
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        $claim = $this->claim($context, $claimUuid);
        $source = Wallet::query()->where('organisation_id', $context->organisation()->getKey())->where('public_id', $request->validated('source_wallet_uuid'))->firstOrFail();
        $beneficiary = $request->validated('beneficiary_uuid') === null ? null : WelfareClaimBeneficiary::query()->where('organisation_id', $context->organisation()->getKey())->where('public_id', $request->validated('beneficiary_uuid'))->firstOrFail();

        return WelfarePaymentResource::make($action->handle($actor, $claim, $source, $beneficiary, $request->integer('amount_minor'), $request->validated('payment_method'), CarbonImmutable::parse($request->validated('payment_date')), $request->idempotencyKey(), $request->validated('recipient_type'), $request->validated('recipient_name')));
    }

    public function show(string $paymentUuid, TenantContext $context, RoleAuthorization $authorization): WelfarePaymentResource
    {
        $authorization->authorize(FoundationPermission::WelfarePaymentsView->value);

        return WelfarePaymentResource::make($this->payment($context, $paymentUuid)->load(['claim', 'beneficiary', 'sourceWallet', 'financialTransaction', 'receipt']));
    }

    public function reversePayment(WelfareFinancialRequest $request, string $paymentUuid, TenantContext $context, ReverseWelfareBenefitPayment $action): WelfarePaymentResource
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);

        return WelfarePaymentResource::make($action->handle($actor, $this->payment($context, $paymentUuid), $request->validated('reason'), $request->idempotencyKey())->load(['claim', 'beneficiary', 'sourceWallet', 'financialTransaction', 'receipt']));
    }

    public function reverseLiability(WelfareFinancialRequest $request, string $liabilityUuid, TenantContext $context, ReverseWelfareClaimLiability $action): JsonResource
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        $liability = WelfareClaimLiability::query()->where('organisation_id', $context->organisation()->getKey())->where('public_id', $liabilityUuid)->firstOrFail();

        return new JsonResource($action->handle($actor, $liability, $request->validated('reason'), $request->idempotencyKey()));
    }

    private function claim(TenantContext $context, string $uuid): WelfareClaim
    {
        return WelfareClaim::query()->where('organisation_id', $context->organisation()->getKey())->where('public_id', $uuid)->firstOrFail();
    }

    private function payment(TenantContext $context, string $uuid): WelfareBenefitPayment
    {
        return WelfareBenefitPayment::query()->where('organisation_id', $context->organisation()->getKey())->where('public_id', $uuid)->firstOrFail();
    }
}
