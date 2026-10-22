<?php

namespace App\Http\Middleware;

use App\Domain\Organisations\Models\Branch;
use App\Domain\Organisations\Models\Organisation;
use App\Domain\Organisations\Services\OrganisationAccessService;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenantContext
{
    public function __construct(
        private TenantContext $tenantContext,
        private OrganisationAccessService $organisationAccess,
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $usesHeaderContext = $request->hasHeader('X-Organisation-ID');
        $organisationPublicId = $usesHeaderContext
            ? $request->header('X-Organisation-ID')
            : $request->session()->get('active_context.organisation_public_id');

        if (! $user instanceof User || ! is_string($organisationPublicId)) {
            return $this->forbidden($request);
        }

        $organisation = Organisation::query()
            ->active()
            ->where('public_id', $organisationPublicId)
            ->first();

        if (
            $organisation === null
            || ! $this->organisationAccess->hasActiveAccess($user, $organisation)
        ) {
            return $this->forbidden($request);
        }

        $branchPublicId = $usesHeaderContext
            ? $request->header('X-Branch-ID')
            : $request->session()->get('active_context.branch_public_id');
        $branch = $this->resolveBranch($branchPublicId, $organisation);

        if (is_string($branchPublicId) && $branch === null) {
            return $this->forbidden($request);
        }

        $this->tenantContext->setOrganisation($organisation);
        $this->tenantContext->setBranch($branch);

        Context::add([
            'organisation_id' => $organisation->getKey(),
            'organisation_public_id' => $organisation->public_id,
            'branch_id' => $branch?->getKey(),
            'branch_public_id' => $branch?->public_id,
        ]);

        return $next($request);
    }

    private function resolveBranch(mixed $branchPublicId, Organisation $organisation): ?Branch
    {
        if (! is_string($branchPublicId)) {
            return null;
        }

        return Branch::query()
            ->active()
            ->forOrganisation($organisation)
            ->where('public_id', $branchPublicId)
            ->first();
    }

    private function forbidden(Request $request): Response
    {
        return ApiResponse::error(
            request: $request,
            code: 'context_invalid',
            message: 'The selected organisation context is unavailable.',
            status: Response::HTTP_FORBIDDEN,
        );
    }
}
