<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Contributions\Actions\ExtendContributionSchedule;
use App\Domain\Contributions\Queries\FindContributionEnrolmentByUuid;
use App\Domain\Contributions\Queries\ListContributionSchedule;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ExtendContributionScheduleRequest;
use App\Http\Requests\Api\V1\ListContributionScheduleRequest;
use App\Http\Resources\Api\V1\ContributionEnrolmentResource;
use App\Http\Resources\Api\V1\ContributionObligationResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ContributionScheduleController extends Controller
{
    public function index(
        ListContributionScheduleRequest $request,
        string $contributionEnrolmentUuid,
        FindContributionEnrolmentByUuid $enrolments,
        ListContributionSchedule $schedule,
    ): AnonymousResourceCollection {
        $enrolment = $enrolments->handle($contributionEnrolmentUuid) ?? abort(404);

        return ContributionObligationResource::collection($schedule->handle(
            $enrolment,
            $request->validated('filter', []),
        ))->additional(['meta' => ['request_id' => ApiResponse::requestId($request)]]);
    }

    public function extend(
        ExtendContributionScheduleRequest $request,
        string $contributionEnrolmentUuid,
        FindContributionEnrolmentByUuid $enrolments,
        ExtendContributionSchedule $extend,
    ): ContributionEnrolmentResource {
        $enrolment = $enrolments->handle($contributionEnrolmentUuid) ?? abort(404);

        return ContributionEnrolmentResource::make(
            $extend->handle($enrolment)->loadMissing(['contributionPlan.organisation', 'member', 'obligations']),
        )->additional(['meta' => ['request_id' => ApiResponse::requestId($request)]]);
    }
}
