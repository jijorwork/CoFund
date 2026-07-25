<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Contributions\Actions\ActivateContributionPlan;
use App\Domain\Contributions\Actions\ArchiveContributionPlan;
use App\Domain\Contributions\Actions\CloseContributionPlan;
use App\Domain\Contributions\Actions\ReinstateContributionPlan;
use App\Domain\Contributions\Actions\SuspendContributionPlan;
use App\Domain\Contributions\Models\ContributionPlan;
use App\Domain\Contributions\Queries\FindContributionPlanByUuid;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ContributionPlanLifecycleRequest;
use App\Http\Resources\Api\V1\ContributionPlanResource;
use App\Http\Responses\ApiResponse;

class ContributionPlanLifecycleController extends Controller
{
    public function activate(ContributionPlanLifecycleRequest $request, string $contributionPlanUuid, FindContributionPlanByUuid $plans, ActivateContributionPlan $action): ContributionPlanResource
    {
        return $this->respond($request, $action->handle($plans->handle($contributionPlanUuid) ?? abort(404)));
    }

    public function suspend(ContributionPlanLifecycleRequest $request, string $contributionPlanUuid, FindContributionPlanByUuid $plans, SuspendContributionPlan $action): ContributionPlanResource
    {
        return $this->respond($request, $action->handle($plans->handle($contributionPlanUuid) ?? abort(404)));
    }

    public function reinstate(ContributionPlanLifecycleRequest $request, string $contributionPlanUuid, FindContributionPlanByUuid $plans, ReinstateContributionPlan $action): ContributionPlanResource
    {
        return $this->respond($request, $action->handle($plans->handle($contributionPlanUuid) ?? abort(404)));
    }

    public function close(ContributionPlanLifecycleRequest $request, string $contributionPlanUuid, FindContributionPlanByUuid $plans, CloseContributionPlan $action): ContributionPlanResource
    {
        return $this->respond($request, $action->handle($plans->handle($contributionPlanUuid) ?? abort(404)));
    }

    public function archive(ContributionPlanLifecycleRequest $request, string $contributionPlanUuid, FindContributionPlanByUuid $plans, ArchiveContributionPlan $action): ContributionPlanResource
    {
        return $this->respond($request, $action->handle($plans->handle($contributionPlanUuid) ?? abort(404)));
    }

    private function respond(ContributionPlanLifecycleRequest $request, ContributionPlan $plan): ContributionPlanResource
    {
        return ContributionPlanResource::make($plan)
            ->additional(['meta' => ['request_id' => ApiResponse::requestId($request)]]);
    }
}
