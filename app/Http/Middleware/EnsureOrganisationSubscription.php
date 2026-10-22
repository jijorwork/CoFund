<?php

namespace App\Http\Middleware;

use App\Domain\Organisations\Tenancy\TenantContext;
use App\Domain\Subscriptions\Services\SubscriptionAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOrganisationSubscription
{
    public function __construct(
        private TenantContext $tenantContext,
        private SubscriptionAccess $subscriptions,
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->subscriptions->allows($this->tenantContext->organisation())) {
            abort(Response::HTTP_PAYMENT_REQUIRED, 'An active organisation subscription is required.');
        }

        return $next($request);
    }
}
