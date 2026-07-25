<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Contributions\Actions\ApproveContributionPlanEnrolment;
use App\Domain\Contributions\Actions\CancelContributionPlanEnrolment;
use App\Domain\Contributions\Actions\EndContributionPlanEnrolment;
use App\Domain\Contributions\Actions\ReinstateContributionPlanEnrolment;
use App\Domain\Contributions\Actions\RejectContributionPlanEnrolment;
use App\Domain\Contributions\Actions\SuspendContributionPlanEnrolment;
use App\Domain\Contributions\Models\MemberPlanEnrolment;
use App\Domain\Contributions\Queries\FindContributionEnrolmentByUuid;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ContributionEnrolmentLifecycleRequest;
use App\Http\Resources\Api\V1\ContributionEnrolmentResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use Carbon\CarbonImmutable;

class ContributionEnrolmentLifecycleController extends Controller
{
    public function approve(ContributionEnrolmentLifecycleRequest $request, string $contributionEnrolmentUuid, FindContributionEnrolmentByUuid $enrolments, ApproveContributionPlanEnrolment $action): ContributionEnrolmentResource
    {
        return $this->respond($request, $action->handle($this->find($enrolments, $contributionEnrolmentUuid), $this->actor($request)));
    }

    public function reject(ContributionEnrolmentLifecycleRequest $request, string $contributionEnrolmentUuid, FindContributionEnrolmentByUuid $enrolments, RejectContributionPlanEnrolment $action): ContributionEnrolmentResource
    {
        return $this->respond($request, $action->handle($this->find($enrolments, $contributionEnrolmentUuid), $this->actor($request), $request->validated('reason')));
    }

    public function suspend(ContributionEnrolmentLifecycleRequest $request, string $contributionEnrolmentUuid, FindContributionEnrolmentByUuid $enrolments, SuspendContributionPlanEnrolment $action): ContributionEnrolmentResource
    {
        return $this->respond($request, $action->handle($this->find($enrolments, $contributionEnrolmentUuid), $this->actor($request), $request->validated('reason')));
    }

    public function reinstate(ContributionEnrolmentLifecycleRequest $request, string $contributionEnrolmentUuid, FindContributionEnrolmentByUuid $enrolments, ReinstateContributionPlanEnrolment $action): ContributionEnrolmentResource
    {
        return $this->respond($request, $action->handle($this->find($enrolments, $contributionEnrolmentUuid)));
    }

    public function end(ContributionEnrolmentLifecycleRequest $request, string $contributionEnrolmentUuid, FindContributionEnrolmentByUuid $enrolments, EndContributionPlanEnrolment $action): ContributionEnrolmentResource
    {
        return $this->respond($request, $action->handle(
            $this->find($enrolments, $contributionEnrolmentUuid),
            $this->actor($request),
            CarbonImmutable::parse($request->validated('effective_date')),
            $request->validated('reason'),
        ));
    }

    public function cancel(ContributionEnrolmentLifecycleRequest $request, string $contributionEnrolmentUuid, FindContributionEnrolmentByUuid $enrolments, CancelContributionPlanEnrolment $action): ContributionEnrolmentResource
    {
        return $this->respond($request, $action->handle($this->find($enrolments, $contributionEnrolmentUuid), $this->actor($request), $request->validated('reason')));
    }

    private function find(FindContributionEnrolmentByUuid $enrolments, string $uuid): MemberPlanEnrolment
    {
        return $enrolments->handle($uuid) ?? abort(404);
    }

    private function actor(ContributionEnrolmentLifecycleRequest $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);

        return $actor;
    }

    private function respond(ContributionEnrolmentLifecycleRequest $request, MemberPlanEnrolment $enrolment): ContributionEnrolmentResource
    {
        return ContributionEnrolmentResource::make($enrolment->loadMissing(['contributionPlan.organisation', 'member', 'obligations']))
            ->additional(['meta' => ['request_id' => ApiResponse::requestId($request)]]);
    }
}
