<?php

namespace App\Http\Middleware;

use App\Domain\Identity\Actions\SwitchActiveRoleContext;
use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Models\OrganisationUserRole;
use App\Domain\Identity\Services\ListAvailableUserRoleContexts;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFilamentContext
{
    public function __construct(
        private readonly ListAvailableUserRoleContexts $contexts,
        private readonly SwitchActiveRoleContext $switchContext,
    ) {}

    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->session()->has('active_context')) {
            return $next($request);
        }

        $user = $request->user();
        if (! $user instanceof User) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $assignment = $this->contexts->forUser($user)
            ->first(fn (OrganisationUserRole $assignment): bool => $assignment->role
                ->checkPermissionTo(FoundationPermission::FilamentAccess->value, 'web'));

        if (! $assignment instanceof OrganisationUserRole) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $this->switchContext->handle(
            $user,
            $assignment->organisation,
            $assignment->branch,
            $assignment,
            $request->session(),
        );

        return $next($request);
    }
}
