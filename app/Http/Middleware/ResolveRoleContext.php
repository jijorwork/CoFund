<?php

namespace App\Http\Middleware;

use App\Domain\Identity\Models\OrganisationUserRole;
use App\Domain\Identity\Services\CheckActiveRoleAssignment;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Symfony\Component\HttpFoundation\Response;

class ResolveRoleContext
{
    public function __construct(
        private TenantContext $tenantContext,
        private CheckActiveRoleAssignment $checkAssignment,
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $assignmentPublicId = $request->session()->get(
            'active_context.role_assignment_public_id',
        );

        if (! $user instanceof User || ! is_string($assignmentPublicId)) {
            return $this->forbidden($request);
        }

        $assignment = OrganisationUserRole::query()
            ->with(['organisation', 'branch', 'role', 'user'])
            ->where('public_id', $assignmentPublicId)
            ->first();

        if (
            $assignment === null
            || ! $this->checkAssignment->passes(
                $assignment,
                $user,
                $this->tenantContext->organisation(),
                $this->tenantContext->branch(),
            )
        ) {
            $request->session()->forget('active_context');

            return $this->forbidden($request);
        }

        $this->tenantContext->setRoleAssignment($assignment);
        Context::add([
            'role_id' => $assignment->role_id,
            'role_assignment_id' => $assignment->getKey(),
            'role_assignment_public_id' => $assignment->public_id,
        ]);

        try {
            return $next($request);
        } finally {
            $this->tenantContext->clearRole();
            Context::forget([
                'role_id',
                'role_assignment_id',
                'role_assignment_public_id',
            ]);
        }
    }

    private function forbidden(Request $request): Response
    {
        return ApiResponse::error(
            request: $request,
            code: 'context_invalid',
            message: 'The selected role context is unavailable.',
            status: Response::HTTP_FORBIDDEN,
        );
    }
}
