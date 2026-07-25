<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Contributions\Queries\ListContributionEnrolments;
use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Membership\Queries\FindMemberByUuid;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ContributionEnrolmentResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MemberContributionSummaryController extends Controller
{
    public function __invoke(
        Request $request,
        string $memberUuid,
        FindMemberByUuid $members,
        ListContributionEnrolments $enrolments,
        RoleAuthorization $authorization,
    ): AnonymousResourceCollection {
        $authorization->authorize(FoundationPermission::ContributionEnrolmentsView->value);
        $members->handle($memberUuid) ?? abort(404);

        return ContributionEnrolmentResource::collection($enrolments->handle(
            ['member_uuid' => $memberUuid],
            '-created_at',
        ))->additional(['meta' => ['request_id' => ApiResponse::requestId($request)]]);
    }
}
