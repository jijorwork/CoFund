<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identity\Actions\AssignRoleToUser;
use App\Domain\Identity\Exceptions\InvalidRoleAssignment;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Services\RoleAssignmentDirectory;
use App\Domain\Organisations\Models\Branch;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListRoleAssignmentsRequest;
use App\Http\Requests\Api\V1\StoreRoleAssignmentRequest;
use App\Http\Resources\Api\V1\RoleAssignmentResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Date;

class RoleAssignmentController extends Controller
{
    public function index(
        ListRoleAssignmentsRequest $request,
        RoleAssignmentDirectory $assignments,
    ): AnonymousResourceCollection {
        return RoleAssignmentResource::collection(
            $assignments->paginate($request->validated('filter', [])),
        )->additional([
            'meta' => ['request_id' => ApiResponse::requestId($request)],
        ]);
    }

    public function store(
        StoreRoleAssignmentRequest $request,
        AssignRoleToUser $assignRole,
        TenantContext $tenantContext,
    ): RoleAssignmentResource {
        $assigningUser = $request->user();
        $targetUser = User::query()->where('public_id', $request->string('user_uuid'))->first();
        $role = Role::query()->where('name', $request->string('role_name'))->first();
        $branch = $request->filled('branch_uuid')
            ? Branch::query()->where('public_id', $request->string('branch_uuid'))->first()
            : null;

        if (
            ! $assigningUser instanceof User
            || $targetUser === null
            || $role === null
            || ($request->filled('branch_uuid') && $branch === null)
        ) {
            throw new InvalidRoleAssignment;
        }

        $assignment = $assignRole->handle(
            assigningUser: $assigningUser,
            organisation: $tenantContext->organisation(),
            targetUser: $targetUser,
            role: $role,
            branch: $branch,
            startsAt: $request->filled('starts_at')
                ? Date::parse($request->string('starts_at')->toString())
                : null,
            endsAt: $request->filled('ends_at')
                ? Date::parse($request->string('ends_at')->toString())
                : null,
        )->load(['organisation', 'branch', 'user', 'role']);

        return RoleAssignmentResource::make($assignment)->additional([
            'meta' => ['request_id' => ApiResponse::requestId($request)],
        ]);
    }

    public function show(
        Request $request,
        string $roleAssignmentUuid,
        RoleAssignmentDirectory $assignments,
    ): RoleAssignmentResource {
        return RoleAssignmentResource::make(
            $assignments->findByPublicId($roleAssignmentUuid) ?? abort(404),
        )->additional([
            'meta' => ['request_id' => ApiResponse::requestId($request)],
        ]);
    }
}
