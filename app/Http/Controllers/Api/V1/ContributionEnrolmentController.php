<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Contributions\Actions\EnrolMemberInContributionPlan;
use App\Domain\Contributions\Queries\FindContributionEnrolmentByUuid;
use App\Domain\Contributions\Queries\FindContributionPlanByUuid;
use App\Domain\Contributions\Queries\ListContributionEnrolments;
use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Membership\Queries\FindMemberByUuid;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListContributionEnrolmentsRequest;
use App\Http\Requests\Api\V1\StoreContributionEnrolmentRequest;
use App\Http\Resources\Api\V1\ContributionEnrolmentResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ContributionEnrolmentController extends Controller
{
    public function index(ListContributionEnrolmentsRequest $request, ListContributionEnrolments $enrolments): AnonymousResourceCollection
    {
        return ContributionEnrolmentResource::collection($enrolments->handle(
            $request->validated('filter', []),
            $request->validated('sort', '-created_at'),
        ))->additional(['meta' => ['request_id' => ApiResponse::requestId($request)]]);
    }

    public function store(
        StoreContributionEnrolmentRequest $request,
        FindMemberByUuid $members,
        FindContributionPlanByUuid $plans,
        EnrolMemberInContributionPlan $enrol,
    ): JsonResponse {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        $member = $members->handle($request->validated('member_uuid')) ?? abort(404);
        $plan = $plans->handle($request->validated('plan_uuid')) ?? abort(404);

        return $this->respond($request, $enrol->handle(
            $actor,
            $member,
            $plan,
            $request->validated('selected_amount_minor'),
            CarbonImmutable::parse($request->validated('start_date')),
            $request->filled('end_date') ? CarbonImmutable::parse($request->validated('end_date')) : null,
        ))->response()->setStatusCode(201);
    }

    public function show(
        Request $request,
        string $contributionEnrolmentUuid,
        FindContributionEnrolmentByUuid $enrolments,
        RoleAuthorization $authorization,
    ): ContributionEnrolmentResource {
        $authorization->authorize(FoundationPermission::ContributionEnrolmentsView->value);

        return $this->respond($request, $enrolments->handle($contributionEnrolmentUuid) ?? abort(404));
    }

    private function respond(Request $request, mixed $enrolment): ContributionEnrolmentResource
    {
        return ContributionEnrolmentResource::make($enrolment)
            ->additional(['meta' => ['request_id' => ApiResponse::requestId($request)]]);
    }
}
