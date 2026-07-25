<?php

use App\Domain\Contributions\Exceptions\InvalidContributionOperation;
use App\Domain\Identity\Exceptions\InvalidRoleAssignment;
use App\Domain\Identity\Exceptions\InvalidRoleContext;
use App\Domain\Identity\Exceptions\PermissionDenied;
use App\Domain\Membership\Exceptions\InvalidMembershipOperation;
use App\Http\Middleware\EnsureApiRequestId;
use App\Http\Middleware\ResolveRoleContext;
use App\Http\Middleware\ResolveTenantContext;
use App\Http\Responses\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            EnsureApiRequestId::class,
        ]);

        $middleware->alias([
            'tenant.context' => ResolveTenantContext::class,
            'role.context' => ResolveRoleContext::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error(
                request: $request,
                code: 'unauthenticated',
                message: 'Authentication is required.',
                status: 401,
            );
        });

        $exceptions->render(function (ValidationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error(
                request: $request,
                code: 'validation_failed',
                message: 'The given data was invalid.',
                status: 422,
                details: $exception->errors(),
            );
        });

        $exceptions->render(function (PermissionDenied $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error(
                request: $request,
                code: 'forbidden',
                message: 'You are not authorized to perform this action.',
                status: 403,
            );
        });

        $exceptions->render(function (AuthorizationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error(
                request: $request,
                code: 'forbidden',
                message: 'You are not authorized to perform this action.',
                status: 403,
            );
        });

        $exceptions->render(function (AccessDeniedHttpException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error(
                request: $request,
                code: 'forbidden',
                message: 'You are not authorized to perform this action.',
                status: 403,
            );
        });

        $exceptions->render(function (InvalidRoleContext $exception, Request $request) {
            return ApiResponse::error(
                request: $request,
                code: 'context_invalid',
                message: 'The selected role context is unavailable.',
                status: 403,
            );
        });

        $exceptions->render(function (InvalidRoleAssignment $exception, Request $request) {
            return ApiResponse::error(
                request: $request,
                code: 'role_assignment_invalid',
                message: 'The role assignment could not be completed.',
                status: 422,
            );
        });

        $exceptions->render(function (InvalidMembershipOperation $exception, Request $request) {
            return ApiResponse::error(
                request: $request,
                code: 'membership_operation_invalid',
                message: 'The membership operation could not be completed.',
                status: 422,
            );
        });

        $exceptions->render(function (InvalidContributionOperation $exception, Request $request) {
            return ApiResponse::error(
                request: $request,
                code: 'contribution_operation_invalid',
                message: 'The contribution operation could not be completed.',
                status: 422,
            );
        });

        $exceptions->render(function (ModelNotFoundException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error(
                request: $request,
                code: 'not_found',
                message: 'The requested resource was not found.',
                status: 404,
            );
        });
    })->create();
