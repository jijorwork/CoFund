<?php

namespace App\Http\Middleware;

use App\Domain\Identity\Models\ContextualAccessToken;
use App\Domain\Identity\Models\OrganisationUserRole;
use App\Domain\Identity\Services\CheckActiveRoleAssignment;
use App\Domain\Organisations\Models\Branch;
use App\Domain\Organisations\Models\Organisation;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Symfony\Component\HttpFoundation\Response;

class ResolveTokenContext
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
        $token = $user instanceof User ? $user->currentAccessToken() : null;

        if (! $token instanceof ContextualAccessToken || ! $token->can('read')) {
            return $this->forbidden($request);
        }

        $organisation = Organisation::query()->active()->find($token->organisation_id);
        $branch = $token->branch_id === null
            ? null
            : Branch::query()->active()->find($token->branch_id);
        $assignment = OrganisationUserRole::query()
            ->with(['organisation', 'branch', 'role', 'user'])
            ->find($token->role_assignment_id);

        if (
            $organisation === null
            || ($token->branch_id !== null && $branch === null)
            || $assignment === null
            || ! $this->checkAssignment->passes($assignment, $user, $organisation, $branch)
        ) {
            return $this->forbidden($request);
        }

        $this->tenantContext->setOrganisation($organisation);
        $this->tenantContext->setBranch($branch);
        $this->tenantContext->setRoleAssignment($assignment);

        Context::add([
            'organisation_id' => $organisation->getKey(),
            'organisation_public_id' => $organisation->public_id,
            'branch_id' => $branch?->getKey(),
            'branch_public_id' => $branch?->public_id,
            'role_id' => $assignment->role_id,
            'role_assignment_id' => $assignment->getKey(),
            'role_assignment_public_id' => $assignment->public_id,
        ]);

        return $next($request);
    }

    private function forbidden(Request $request): Response
    {
        return ApiResponse::error(
            request: $request,
            code: 'context_invalid',
            message: 'The token context is unavailable.',
            status: Response::HTTP_FORBIDDEN,
        );
    }
}
