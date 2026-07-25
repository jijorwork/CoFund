<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Membership\Actions\CreateMember;
use App\Domain\Membership\Actions\UpdateMemberProfile;
use App\Domain\Membership\Queries\FindMemberByUuid;
use App\Domain\Membership\Queries\ListMembers;
use App\Domain\Organisations\Models\Branch;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListMembersRequest;
use App\Http\Requests\Api\V1\StoreMemberRequest;
use App\Http\Requests\Api\V1\UpdateMemberRequest;
use App\Http\Resources\Api\V1\MemberResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MemberController extends Controller
{
    public function index(ListMembersRequest $request, ListMembers $members): AnonymousResourceCollection
    {
        return MemberResource::collection($members->handle(
            $request->validated('filter', []),
            $request->validated('sort', '-created_at'),
        ))->additional(['meta' => ['request_id' => ApiResponse::requestId($request)]]);
    }

    public function store(
        StoreMemberRequest $request,
        CreateMember $create,
        TenantContext $context,
    ): MemberResource {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        $branch = $this->branch($request->validated('branch_uuid'), $context);
        $profile = collect($request->validated())->except('branch_uuid')->all();

        return MemberResource::make($create->handle($actor, $profile, $branch))
            ->additional(['meta' => ['request_id' => ApiResponse::requestId($request)]]);
    }

    public function show(
        Request $request,
        string $memberUuid,
        FindMemberByUuid $members,
        RoleAuthorization $authorization,
    ): MemberResource {
        $authorization->authorize(FoundationPermission::MembersView->value);

        return MemberResource::make($members->handle($memberUuid) ?? abort(404))
            ->additional(['meta' => ['request_id' => ApiResponse::requestId($request)]]);
    }

    public function update(
        UpdateMemberRequest $request,
        string $memberUuid,
        FindMemberByUuid $members,
        UpdateMemberProfile $update,
        TenantContext $context,
    ): MemberResource {
        $member = $members->handle($memberUuid) ?? abort(404);
        $branch = $request->has('branch_uuid')
            ? $this->branch($request->validated('branch_uuid'), $context)
            : $member->branch;
        $profile = collect($request->validated())->except('branch_uuid')->all();

        return MemberResource::make($update->handle($member, $profile, $branch))
            ->additional(['meta' => ['request_id' => ApiResponse::requestId($request)]]);
    }

    private function branch(?string $uuid, TenantContext $context): ?Branch
    {
        if ($uuid === null) {
            return null;
        }

        return Branch::query()
            ->whereBelongsTo($context->organisation())
            ->where('public_id', $uuid)
            ->first() ?? abort(404);
    }
}
