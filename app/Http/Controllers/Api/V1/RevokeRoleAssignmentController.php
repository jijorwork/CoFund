<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identity\Actions\RevokeUserRole;
use App\Domain\Identity\Exceptions\InvalidRoleAssignment;
use App\Domain\Identity\Services\RoleAssignmentDirectory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RevokeRoleAssignmentRequest;
use App\Http\Resources\Api\V1\RoleAssignmentResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;

class RevokeRoleAssignmentController extends Controller
{
    public function __invoke(
        RevokeRoleAssignmentRequest $request,
        string $roleAssignmentUuid,
        RoleAssignmentDirectory $assignments,
        RevokeUserRole $revokeRole,
    ): RoleAssignmentResource {
        $assignment = $assignments->findByPublicId($roleAssignmentUuid);
        $user = $request->user();

        if ($assignment === null || ! $user instanceof User) {
            throw new InvalidRoleAssignment;
        }

        return RoleAssignmentResource::make(
            $revokeRole->handle(
                $assignment,
                $user,
                $request->filled('reason') ? $request->string('reason')->toString() : null,
            )
                ->load(['organisation', 'branch', 'user', 'role']),
        )->additional([
            'meta' => ['request_id' => ApiResponse::requestId($request)],
        ]);
    }
}
