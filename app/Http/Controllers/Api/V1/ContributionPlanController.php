<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Contributions\Actions\CreateContributionPlan;
use App\Domain\Contributions\Actions\UpdateContributionPlan;
use App\Domain\Contributions\Queries\FindContributionPlanByUuid;
use App\Domain\Contributions\Queries\ListContributionPlans;
use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Membership\Models\Group;
use App\Domain\Organisations\Models\Branch;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ContributionPlanRequest;
use App\Http\Requests\Api\V1\ListContributionPlansRequest;
use App\Http\Resources\Api\V1\ContributionPlanResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ContributionPlanController extends Controller
{
    public function index(ListContributionPlansRequest $request, ListContributionPlans $plans): AnonymousResourceCollection
    {
        return ContributionPlanResource::collection($plans->handle(
            $request->validated('filter', []),
            $request->validated('sort', 'name'),
        ))->additional(['meta' => ['request_id' => ApiResponse::requestId($request)]]);
    }

    public function store(
        ContributionPlanRequest $request,
        CreateContributionPlan $create,
        TenantContext $context,
    ): ContributionPlanResource {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        [$branch, $group] = $this->scope($request, $context);
        $attributes = collect($request->validated())->except(['branch_uuid', 'group_uuid'])->all();

        return ContributionPlanResource::make($create->handle($actor, $attributes, $branch, $group))
            ->additional(['meta' => ['request_id' => ApiResponse::requestId($request)]]);
    }

    public function show(
        Request $request,
        string $contributionPlanUuid,
        FindContributionPlanByUuid $plans,
    ): ContributionPlanResource {
        app(RoleAuthorization::class)
            ->authorize(FoundationPermission::ContributionPlansView->value);

        return ContributionPlanResource::make($plans->handle($contributionPlanUuid) ?? abort(404))
            ->additional(['meta' => ['request_id' => ApiResponse::requestId($request)]]);
    }

    public function update(
        ContributionPlanRequest $request,
        string $contributionPlanUuid,
        FindContributionPlanByUuid $plans,
        UpdateContributionPlan $update,
        TenantContext $context,
    ): ContributionPlanResource {
        $plan = $plans->handle($contributionPlanUuid) ?? abort(404);
        [$branch, $group] = $this->scope($request, $context, $plan->branch, $plan->group);
        $attributes = collect($request->validated())->except(['branch_uuid', 'group_uuid'])->all();

        return ContributionPlanResource::make($update->handle($plan, $attributes, $branch, $group))
            ->additional(['meta' => ['request_id' => ApiResponse::requestId($request)]]);
    }

    /** @return array{0: Branch|null, 1: Group|null} */
    private function scope(
        Request $request,
        TenantContext $context,
        ?Branch $currentBranch = null,
        ?Group $currentGroup = null,
    ): array {
        $branch = $request->has('branch_uuid')
            ? $this->branch($request->input('branch_uuid'), $context)
            : $currentBranch;
        $group = $request->has('group_uuid')
            ? $this->group($request->input('group_uuid'), $context)
            : $currentGroup;

        return [$branch, $group];
    }

    private function branch(?string $uuid, TenantContext $context): ?Branch
    {
        return $uuid === null ? null : Branch::query()
            ->whereBelongsTo($context->organisation())
            ->where('public_id', $uuid)
            ->first() ?? abort(404);
    }

    private function group(?string $uuid, TenantContext $context): ?Group
    {
        return $uuid === null ? null : Group::query()
            ->whereBelongsTo($context->organisation())
            ->where('public_id', $uuid)
            ->first() ?? abort(404);
    }
}
