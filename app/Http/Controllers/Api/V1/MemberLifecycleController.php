<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Membership\Actions\ActivateMember;
use App\Domain\Membership\Actions\ExitMember;
use App\Domain\Membership\Actions\MarkMemberDeceased;
use App\Domain\Membership\Actions\MarkMemberInactive;
use App\Domain\Membership\Actions\ReinstateMember;
use App\Domain\Membership\Actions\SuspendMember;
use App\Domain\Membership\Queries\FindMemberByUuid;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\MemberLifecycleRequest;
use App\Http\Resources\Api\V1\MemberResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use Illuminate\Support\Facades\Date;

class MemberLifecycleController extends Controller
{
    public function activate(MemberLifecycleRequest $request, string $memberUuid, FindMemberByUuid $members, ActivateMember $action): MemberResource
    {
        return $this->response($request, $action->handle($members->handle($memberUuid) ?? abort(404), $this->actor($request)));
    }

    public function suspend(MemberLifecycleRequest $request, string $memberUuid, FindMemberByUuid $members, SuspendMember $action): MemberResource
    {
        return $this->response($request, $action->handle($members->handle($memberUuid) ?? abort(404), $this->actor($request), $request->string('reason')->toString()));
    }

    public function reinstate(MemberLifecycleRequest $request, string $memberUuid, FindMemberByUuid $members, ReinstateMember $action): MemberResource
    {
        return $this->response($request, $action->handle($members->handle($memberUuid) ?? abort(404), $this->actor($request)));
    }

    public function markInactive(MemberLifecycleRequest $request, string $memberUuid, FindMemberByUuid $members, MarkMemberInactive $action): MemberResource
    {
        return $this->response($request, $action->handle($members->handle($memberUuid) ?? abort(404), $this->actor($request), $request->validated('reason')));
    }

    public function exit(MemberLifecycleRequest $request, string $memberUuid, FindMemberByUuid $members, ExitMember $action): MemberResource
    {
        return $this->response($request, $action->handle($members->handle($memberUuid) ?? abort(404), $this->actor($request), Date::parse($request->validated('effective_date')), $request->validated('reason')));
    }

    public function markDeceased(MemberLifecycleRequest $request, string $memberUuid, FindMemberByUuid $members, MarkMemberDeceased $action): MemberResource
    {
        return $this->response($request, $action->handle($members->handle($memberUuid) ?? abort(404), $this->actor($request), Date::parse($request->validated('effective_date')), $request->validated('reason')));
    }

    private function actor(MemberLifecycleRequest $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);

        return $actor;
    }

    private function response(MemberLifecycleRequest $request, mixed $member): MemberResource
    {
        return MemberResource::make($member)->additional([
            'meta' => ['request_id' => ApiResponse::requestId($request)],
        ]);
    }
}
