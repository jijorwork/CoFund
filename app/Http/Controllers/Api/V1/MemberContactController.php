<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Membership\Actions\AddMemberContact;
use App\Domain\Membership\Actions\DeactivateMemberContact;
use App\Domain\Membership\Actions\UpdateMemberContact;
use App\Domain\Membership\Actions\VerifyMemberContact;
use App\Domain\Membership\Models\MemberContact;
use App\Domain\Membership\Queries\FindMemberByUuid;
use App\Domain\Membership\Queries\ListMemberContacts;
use App\Domain\Membership\Services\BeneficiaryAllocationService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\MemberContactRequest;
use App\Http\Resources\Api\V1\MemberContactResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MemberContactController extends Controller
{
    public function index(Request $request, string $memberUuid, FindMemberByUuid $members, ListMemberContacts $contacts, RoleAuthorization $authorization): AnonymousResourceCollection
    {
        $authorization->authorize(FoundationPermission::MembersManageContacts->value);
        $member = $members->handle($memberUuid) ?? abort(404);

        return MemberContactResource::collection($contacts->handle($member))
            ->additional(['meta' => ['request_id' => ApiResponse::requestId($request)]]);
    }

    public function store(MemberContactRequest $request, string $memberUuid, FindMemberByUuid $members, AddMemberContact $action, BeneficiaryAllocationService $allocation): MemberContactResource
    {
        $member = $members->handle($memberUuid) ?? abort(404);
        $contact = $action->handle($member, $request->validated());

        return MemberContactResource::make($contact)->additional(['meta' => [
            'request_id' => ApiResponse::requestId($request),
            'remaining_beneficiary_percentage' => $allocation->remainingPercentage($member),
        ]]);
    }

    public function show(Request $request, string $memberUuid, string $contactUuid, FindMemberByUuid $members, RoleAuthorization $authorization): MemberContactResource
    {
        $authorization->authorize(FoundationPermission::MembersManageContacts->value);
        $member = $members->handle($memberUuid) ?? abort(404);

        return MemberContactResource::make($this->contact($member->getKey(), $contactUuid))
            ->additional(['meta' => ['request_id' => ApiResponse::requestId($request)]]);
    }

    public function update(MemberContactRequest $request, string $memberUuid, string $contactUuid, FindMemberByUuid $members, UpdateMemberContact $action): MemberContactResource
    {
        $member = $members->handle($memberUuid) ?? abort(404);

        return MemberContactResource::make($action->handle(
            $member,
            $this->contact($member->getKey(), $contactUuid),
            $request->validated(),
        ))->additional(['meta' => ['request_id' => ApiResponse::requestId($request)]]);
    }

    public function deactivate(MemberContactRequest $request, string $memberUuid, string $contactUuid, FindMemberByUuid $members, DeactivateMemberContact $action): MemberContactResource
    {
        $member = $members->handle($memberUuid) ?? abort(404);

        return MemberContactResource::make($action->handle($member, $this->contact($member->getKey(), $contactUuid)))
            ->additional(['meta' => ['request_id' => ApiResponse::requestId($request)]]);
    }

    public function verify(Request $request, string $memberUuid, string $contactUuid, FindMemberByUuid $members, VerifyMemberContact $action, RoleAuthorization $authorization): MemberContactResource
    {
        $authorization->authorize(FoundationPermission::MembersVerifyContacts->value);
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        $member = $members->handle($memberUuid) ?? abort(404);

        return MemberContactResource::make($action->handle($member, $this->contact($member->getKey(), $contactUuid), $actor))
            ->additional(['meta' => ['request_id' => ApiResponse::requestId($request)]]);
    }

    private function contact(int|string $memberId, string $uuid): MemberContact
    {
        return MemberContact::query()
            ->where('member_id', $memberId)
            ->where('public_id', $uuid)
            ->first() ?? abort(404);
    }
}
