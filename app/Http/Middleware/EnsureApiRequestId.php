<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiRequestId
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $providedRequestId = $request->headers->get('X-Request-ID');
        $requestId = is_string($providedRequestId)
            && preg_match('/\A[A-Za-z0-9][A-Za-z0-9._:-]{0,99}\z/', $providedRequestId) === 1
                ? $providedRequestId
                : (string) Str::uuid7();

        $request->attributes->set('request_id', $requestId);
        Context::add('request_id', $requestId);

        try {
            $response = $next($request);
            $response->headers->set('X-Request-ID', $requestId);

            return $response;
        } finally {
            Context::forget('request_id');
        }
    }
}
