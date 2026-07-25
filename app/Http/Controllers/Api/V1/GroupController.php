<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Membership\Actions\AddMemberToGroup;
use App\Domain\Membership\Actions\CreateGroup;
use App\Domain\Membership\Actions\RemoveMemberFromGroup;
use App\Domain\Membership\Actions\UpdateGroup;
use App\Domain\Membership\Queries\FindGroupByUuid;
use App\Domain\Membership\Queries\FindMemberByUuid;
use App\Domain\Membership\Queries\ListGroups;
use App\Domain\Organisations\Models\Branch;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\GroupMembershipRequest;
use App\Http\Requests\Api\V1\GroupRequest;
use App\Http\Requests\Api\V1\ListGroupsRequest;
use App\Http\Resources\Api\V1\GroupResource;
use App\Http\Resources\Api\V1\MemberResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class GroupController extends Controller
{
    public function index(ListGroupsRequest $request, ListGroups $groups): AnonymousResourceCollection
    {
        return GroupResource::collection($groups->handle($request->validated('filter', [])))
            ->additional(['meta' => ['request_id' => ApiResponse::requestId($request)]]);
    }

    public function store(GroupRequest $request, CreateGroup $action, TenantContext $context): GroupResource
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        $branch = $this->branch($request->validated('branch_uuid'), $context);

        return GroupResource::make($action->handle($actor, collect($request->validated())->except('branch_uuid')->all(), $branch))
            ->additional(['meta' => ['request_id' => ApiResponse::requestId($request)]]);
    }

    public function show(Request $request, string $groupUuid, FindGroupByUuid $groups, RoleAuthorization $authorization): GroupResource
    {
        $authorization->authorize(FoundationPermission::GroupsView->value);

        return GroupResource::make($groups->handle($groupUuid) ?? abort(404))
            ->additional(['meta' => ['request_id' => ApiResponse::requestId($request)]]);
    }

    public function members(Request $request, string $groupUuid, FindGroupByUuid $groups, ListGroups $list, RoleAuthorization $authorization): AnonymousResourceCollection
    {
        $authorization->authorize(FoundationPermission::GroupsView->value);
        $group = $groups->handle($groupUuid) ?? abort(404);

        return MemberResource::collection($list->members($group))
            ->additional(['meta' => ['request_id' => ApiResponse::requestId($request)]]);
    }

    public function update(GroupRequest $request, string $groupUuid, FindGroupByUuid $groups, UpdateGroup $action, TenantContext $context): GroupResource
    {
        $group = $groups->handle($groupUuid) ?? abort(404);
        $branch = $request->has('branch_uuid') ? $this->branch($request->validated('branch_uuid'), $context) : $group->branch;

        return GroupResource::make($action->handle($group, collect($request->validated())->except('branch_uuid')->all(), $branch))
            ->additional(['meta' => ['request_id' => ApiResponse::requestId($request)]]);
    }

    public function addMember(GroupMembershipRequest $request, string $groupUuid, FindGroupByUuid $groups, FindMemberByUuid $members, AddMemberToGroup $action): Response
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        $action->handle(
            $groups->handle($groupUuid) ?? abort(404),
            $members->handle($request->validated('member_uuid')) ?? abort(404),
            $actor,
        );

        return response()->noContent();
    }

    public function removeMember(GroupMembershipRequest $request, string $groupUuid, string $memberUuid, FindGroupByUuid $groups, FindMemberByUuid $members, RemoveMemberFromGroup $action): Response
    {
        $action->handle(
            $groups->handle($groupUuid) ?? abort(404),
            $members->handle($memberUuid) ?? abort(404),
        );

        return response()->noContent();
    }

    private function branch(?string $uuid, TenantContext $context): ?Branch
    {
        return $uuid === null ? null : Branch::query()
            ->whereBelongsTo($context->organisation())
            ->where('public_id', $uuid)
            ->first() ?? abort(404);
    }
}
