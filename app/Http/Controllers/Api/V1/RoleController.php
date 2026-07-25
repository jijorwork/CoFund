<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Enums\SystemRole;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\RoleResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RoleController extends Controller
{
    public function __invoke(
        Request $request,
        RoleAuthorization $authorization,
    ): AnonymousResourceCollection {
        $authorization->authorize(FoundationPermission::RolesView->value);

        $roles = Role::query()
            ->whereNot('name', SystemRole::PlatformAdmin->value)
            ->orderBy('name')
            ->get();

        return RoleResource::collection($roles)->additional([
            'meta' => ['request_id' => ApiResponse::requestId($request)],
        ]);
    }
}
