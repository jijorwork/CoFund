<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Membership\Actions\LinkMemberToUser;
use App\Domain\Membership\Actions\UnlinkMemberFromUser;
use App\Domain\Membership\Exceptions\InvalidMembershipOperation;
use App\Domain\Membership\Queries\FindMemberByUuid;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LinkMemberUserRequest;
use App\Http\Resources\Api\V1\MemberResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use Illuminate\Http\Request;

class MemberUserLinkController extends Controller
{
    public function link(
        LinkMemberUserRequest $request,
        string $memberUuid,
        FindMemberByUuid $members,
        LinkMemberToUser $action,
    ): MemberResource {
        $user = User::query()->where('public_id', $request->validated('user_uuid'))->first();
        throw_if($user === null, InvalidMembershipOperation::class);

        return MemberResource::make($action->handle(
            $members->handle($memberUuid) ?? abort(404),
            $user,
        ))->additional(['meta' => ['request_id' => ApiResponse::requestId($request)]]);
    }

    public function unlink(
        Request $request,
        string $memberUuid,
        FindMemberByUuid $members,
        UnlinkMemberFromUser $action,
        RoleAuthorization $authorization,
    ): MemberResource {
        $authorization->authorize(FoundationPermission::MembersLinkUser->value);

        return MemberResource::make($action->handle($members->handle($memberUuid) ?? abort(404)))
            ->additional(['meta' => ['request_id' => ApiResponse::requestId($request)]]);
    }
}
