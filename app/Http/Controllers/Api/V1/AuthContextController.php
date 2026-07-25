<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identity\Actions\SwitchActiveRoleContext;
use App\Domain\Identity\Exceptions\InvalidRoleContext;
use App\Domain\Identity\Models\OrganisationUserRole;
use App\Domain\Identity\Services\ListAvailableUserRoleContexts;
use App\Domain\Organisations\Models\Branch;
use App\Domain\Organisations\Models\Organisation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SwitchActiveContextRequest;
use App\Http\Resources\Api\V1\AvailableContextResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

class AuthContextController extends Controller
{
    public function index(
        Request $request,
        ListAvailableUserRoleContexts $contexts,
    ): AnonymousResourceCollection {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new InvalidRoleContext;
        }

        return AvailableContextResource::collection($contexts->forUser($user))
            ->additional([
                'meta' => ['request_id' => ApiResponse::requestId($request)],
            ]);
    }

    public function store(
        SwitchActiveContextRequest $request,
        SwitchActiveRoleContext $switchContext,
    ): JsonResource {
        $user = $request->user();
        $organisation = Organisation::query()
            ->where('public_id', $request->string('organisation_uuid'))
            ->first();
        $branch = $request->filled('branch_uuid')
            ? Branch::query()->where('public_id', $request->string('branch_uuid'))->first()
            : null;
        $assignment = OrganisationUserRole::query()
            ->with(['organisation', 'branch', 'role', 'user'])
            ->where('public_id', $request->string('role_assignment_uuid'))
            ->first();

        if (
            ! $user instanceof User
            || $organisation === null
            || ($request->filled('branch_uuid') && $branch === null)
            || $assignment === null
        ) {
            throw new InvalidRoleContext;
        }

        $switchContext->handle($user, $organisation, $branch, $assignment, $request->session());

        return AvailableContextResource::make($assignment)->additional([
            'meta' => ['request_id' => ApiResponse::requestId($request)],
        ]);
    }
}
