<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Collections\Actions\RecordContributionPayment;
use App\Domain\Collections\Actions\ReverseContributionPayment;
use App\Domain\Collections\Data\ContributionPaymentData;
use App\Domain\Collections\Data\PaymentAllocationData;
use App\Domain\Collections\Enums\ContributionPaymentMethod;
use App\Domain\Collections\Enums\PaymentAllocationStrategy;
use App\Domain\Collections\Queries\FindContributionPaymentByUuid;
use App\Domain\Collections\Queries\ListContributionPayments;
use App\Domain\Collections\Services\ResolveContributionPaymentSource;
use App\Domain\Contributions\Models\ContributionObligation;
use App\Domain\Contributions\Queries\FindContributionEnrolmentByUuid;
use App\Domain\Contributions\Queries\FindContributionPlanByUuid;
use App\Domain\Membership\Queries\FindMemberByUuid;
use App\Domain\Wallets\Queries\FindWalletByUuid;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListContributionPaymentsRequest;
use App\Http\Requests\Api\V1\ReverseContributionPaymentRequest;
use App\Http\Requests\Api\V1\StoreContributionPaymentRequest;
use App\Http\Resources\Api\V1\ContributionPaymentResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ContributionPaymentController extends Controller
{
    public function index(ListContributionPaymentsRequest $request, ListContributionPayments $payments): AnonymousResourceCollection
    {
        return ContributionPaymentResource::collection($payments->handle(
            $request->validated('filter', []),
            $request->validated('sort', '-payment_date'),
        ))->additional(['meta' => ['request_id' => ApiResponse::requestId($request)]]);
    }

    public function store(
        StoreContributionPaymentRequest $request,
        RecordContributionPayment $record,
        ResolveContributionPaymentSource $sources,
        FindMemberByUuid $members,
        FindContributionEnrolmentByUuid $enrolments,
        FindContributionPlanByUuid $plans,
        FindWalletByUuid $wallets,
    ): JsonResponse {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        $member = $members->handle($request->validated('member_uuid')) ?? abort(404);
        $enrolment = $request->filled('enrolment_uuid')
            ? $enrolments->handle($request->validated('enrolment_uuid')) ?? abort(404)
            : null;
        $plan = $request->filled('plan_uuid')
            ? $plans->handle($request->validated('plan_uuid')) ?? abort(404)
            : $enrolment?->contributionPlan;
        $sourceWallet = $wallets->handle($request->validated('source_wallet_uuid')) ?? abort(404);
        $method = ContributionPaymentMethod::from($request->validated('payment_method'));
        $resolved = $sources->resolve($actor, $member, $method, $sourceWallet);
        $selected = [];

        foreach ($request->validated('allocations', []) as $item) {
            $obligation = ContributionObligation::query()
                ->whereBelongsTo($enrolment ?? abort(422), 'enrolment')
                ->where('public_id', $item['obligation_uuid'])
                ->first() ?? abort(404);
            $selected[] = new PaymentAllocationData($obligation, (int) $item['amount_minor']);
        }

        $payment = $record->handle($actor, new ContributionPaymentData(
            member: $member,
            enrolment: $enrolment,
            plan: $plan,
            amountMinor: (int) $request->validated('amount_minor'),
            currencyCode: $request->validated('currency_code'),
            paymentMethod: $method,
            paymentDate: CarbonImmutable::parse($request->validated('payment_date')),
            sourceWallet: $resolved['source'],
            destinationWallet: $resolved['destination'],
            allocationStrategy: PaymentAllocationStrategy::from($request->validated('allocation_strategy')),
            idempotencyKey: $request->validated('idempotency_key'),
            selectedAllocations: $selected,
            externalReference: $request->validated('external_reference'),
            payerName: $request->validated('payer_name'),
            payerPhone: $request->validated('payer_phone'),
            notes: $request->validated('notes'),
        ));
        $resource = ContributionPaymentResource::make($payment)
            ->additional(['meta' => ['request_id' => ApiResponse::requestId($request)]]);

        return $resource->response()->setStatusCode(201);
    }

    public function show(
        Request $request,
        string $paymentUuid,
        FindContributionPaymentByUuid $payments,
    ): ContributionPaymentResource {
        return ContributionPaymentResource::make($payments->handle($paymentUuid) ?? abort(404))
            ->additional(['meta' => ['request_id' => ApiResponse::requestId($request)]]);
    }

    public function reverse(
        ReverseContributionPaymentRequest $request,
        string $paymentUuid,
        FindContributionPaymentByUuid $payments,
        ReverseContributionPayment $reverse,
    ): ContributionPaymentResource {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);

        return ContributionPaymentResource::make($reverse->handle(
            $payments->handle($paymentUuid) ?? abort(404),
            $actor,
            $request->validated('reason'),
            $request->validated('idempotency_key'),
        ))->additional(['meta' => ['request_id' => ApiResponse::requestId($request)]]);
    }
}
