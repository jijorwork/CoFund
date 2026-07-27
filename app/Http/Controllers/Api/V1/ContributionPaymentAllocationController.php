<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Collections\Queries\FindContributionPaymentByUuid;
use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ContributionPaymentAllocationResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ContributionPaymentAllocationController extends Controller
{
    public function __invoke(
        Request $request,
        string $paymentUuid,
        FindContributionPaymentByUuid $payments,
        RoleAuthorization $authorization,
    ): AnonymousResourceCollection {
        $authorization->authorize(FoundationPermission::ContributionPaymentsView->value);
        $payment = $payments->handle($paymentUuid) ?? abort(404);

        return ContributionPaymentAllocationResource::collection(
            $payment->allocations()->with('obligation.enrolment')->paginate(25),
        )->additional(['meta' => ['request_id' => ApiResponse::requestId($request)]]);
    }
}
