<?php

namespace App\Console\Commands;

use App\Http\Middleware\ResolveRoleContext;
use App\Http\Middleware\ResolveTenantContext;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Str;

#[Signature('api:documentation')]
#[Description('Generate the CoFund API route inventory and OpenAPI contract')]
class GenerateApiDocumentation extends Command
{
    public function handle(): int
    {
        $routes = $this->apiRoutes();

        File::ensureDirectoryExists(base_path('docs'));
        File::put(
            base_path('docs/openapi.json'),
            json_encode(
                $this->openApiDocument($routes),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
            ).PHP_EOL,
        );
        File::put(
            base_path('docs/api-route-inventory.md'),
            $this->routeInventory($routes),
        );

        $this->components->info(
            sprintf('Documented %d API v1 routes.', count($routes)),
        );

        return self::SUCCESS;
    }

    /**
     * @return list<Route>
     */
    private function apiRoutes(): array
    {
        $routes = [];

        foreach (RouteFacade::getRoutes()->getRoutes() as $route) {
            if (Str::startsWith($route->uri(), 'api/v1/')) {
                $routes[] = $route;
            }
        }

        usort(
            $routes,
            fn (Route $left, Route $right): int => [
                $left->uri(),
                implode('|', $left->methods()),
            ] <=> [
                $right->uri(),
                implode('|', $right->methods()),
            ],
        );

        return $routes;
    }

    /**
     * @param  list<Route>  $routes
     * @return array<string, mixed>
     */
    private function openApiDocument(array $routes): array
    {
        $paths = [];
        $tags = [];

        foreach ($routes as $route) {
            $tag = $this->domainFor($route);
            $tags[$tag] = ['name' => $tag];

            foreach ($this->documentedMethods($route) as $method) {
                $path = '/'.Str::after($route->uri(), 'api/v1/');
                $paths[$path][Str::lower($method)] = $this->operation($route, $tag, $method);
            }
        }

        ksort($paths);
        ksort($tags);

        return [
            'openapi' => '3.1.0',
            'info' => [
                'title' => 'CoFund API',
                'version' => '1.0.0',
                'description' => 'The generated contract for CoFund API v1. Business operations require a verified user session and active organisation/role context.',
            ],
            'servers' => [
                ['url' => '/api/v1', 'description' => 'Current host'],
            ],
            'tags' => array_values($tags),
            'paths' => $paths,
            'components' => $this->components(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function operation(Route $route, string $tag, string $method): array
    {
        $middleware = array_values(array_filter(
            $route->gatherMiddleware(),
            is_string(...),
        ));
        $authenticated = $this->hasMiddleware(
            $middleware,
            'auth',
            'Illuminate\Auth\Middleware\Authenticate',
        );
        $tenantContext = $this->hasMiddleware(
            $middleware,
            'tenant.context',
            ResolveTenantContext::class,
        );
        $roleContext = $this->hasMiddleware(
            $middleware,
            'role.context',
            ResolveRoleContext::class,
        );
        $routeName = $route->getName() ?? $route->uri();
        $responses = [
            '200' => ['$ref' => '#/components/responses/Success'],
            '422' => ['$ref' => '#/components/responses/ValidationError'],
            '429' => ['$ref' => '#/components/responses/RateLimited'],
        ];

        if ($authenticated) {
            $responses['401'] = ['$ref' => '#/components/responses/Unauthenticated'];
        }

        if ($tenantContext || $roleContext) {
            $responses['403'] = ['$ref' => '#/components/responses/Forbidden'];
        }

        $operation = [
            'tags' => [$tag],
            'summary' => Str::of($routeName)
                ->after('api.v1.')
                ->replace(['.', '-'], ' ')
                ->headline()
                ->toString(),
            'operationId' => Str::of($routeName)
                ->replace(['api.v1.', '.', '-'], ['', '_', '_'])
                ->toString(),
            'parameters' => $this->parameters($route, $tenantContext, $method),
            'responses' => $responses,
            'x-cofund-route-name' => $routeName,
            'x-cofund-action' => $route->getActionName(),
            'x-cofund-tenant-context' => $tenantContext,
            'x-cofund-role-context' => $roleContext,
        ];

        if ($authenticated) {
            $operation['security'] = [['cookieAuth' => []]];
        }

        return $operation;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parameters(Route $route, bool $tenantContext, string $method): array
    {
        $parameters = [];

        foreach ($route->parameterNames() as $parameterName) {
            $schema = ['type' => 'string'];

            if (Str::endsWith($parameterName, 'Uuid')) {
                $schema['format'] = 'uuid';
            }

            $parameters[] = [
                'name' => $parameterName,
                'in' => 'path',
                'required' => true,
                'schema' => $schema,
            ];
        }

        $parameters[] = ['$ref' => '#/components/parameters/RequestId'];

        if ($tenantContext) {
            $parameters[] = ['$ref' => '#/components/parameters/OrganisationContext'];
            $parameters[] = ['$ref' => '#/components/parameters/BranchContext'];
        }

        if (! in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            $parameters[] = ['$ref' => '#/components/parameters/IdempotencyKey'];
        }

        return $parameters;
    }

    /**
     * @return array<string, mixed>
     */
    private function components(): array
    {
        return [
            'securitySchemes' => [
                'cookieAuth' => [
                    'type' => 'apiKey',
                    'in' => 'cookie',
                    'name' => config('session.cookie', 'cofund_session'),
                    'description' => 'First-party Fortify web session. State-changing requests also require CSRF protection.',
                ],
            ],
            'parameters' => [
                'RequestId' => [
                    'name' => 'X-Request-ID',
                    'in' => 'header',
                    'required' => false,
                    'schema' => ['type' => 'string', 'maxLength' => 100],
                ],
                'OrganisationContext' => [
                    'name' => 'X-Organisation-ID',
                    'in' => 'header',
                    'required' => false,
                    'description' => 'Verified organisation public UUID. When omitted, the active session context is used.',
                    'schema' => ['type' => 'string', 'format' => 'uuid'],
                ],
                'BranchContext' => [
                    'name' => 'X-Branch-ID',
                    'in' => 'header',
                    'required' => false,
                    'description' => 'Optional verified branch public UUID. Valid only with X-Organisation-ID.',
                    'schema' => ['type' => 'string', 'format' => 'uuid'],
                ],
                'IdempotencyKey' => [
                    'name' => 'Idempotency-Key',
                    'in' => 'header',
                    'required' => false,
                    'description' => 'Required by documented retry-sensitive financial commands.',
                    'schema' => ['type' => 'string', 'maxLength' => 255],
                ],
            ],
            'schemas' => [
                'SuccessEnvelope' => [
                    'type' => 'object',
                    'required' => ['data', 'meta'],
                    'properties' => [
                        'data' => true,
                        'meta' => ['$ref' => '#/components/schemas/ResponseMeta'],
                    ],
                ],
                'ErrorEnvelope' => [
                    'type' => 'object',
                    'required' => ['error', 'meta'],
                    'properties' => [
                        'error' => [
                            'type' => 'object',
                            'required' => ['code', 'message', 'details'],
                            'properties' => [
                                'code' => ['type' => 'string'],
                                'message' => ['type' => 'string'],
                                'details' => ['type' => 'object', 'additionalProperties' => true],
                            ],
                        ],
                        'meta' => ['$ref' => '#/components/schemas/ResponseMeta'],
                    ],
                ],
                'ResponseMeta' => [
                    'type' => 'object',
                    'required' => ['request_id'],
                    'properties' => [
                        'request_id' => ['type' => 'string'],
                    ],
                ],
            ],
            'responses' => [
                'Success' => [
                    'description' => 'Successful response.',
                    'headers' => [
                        'X-Request-ID' => ['$ref' => '#/components/headers/RequestId'],
                    ],
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/SuccessEnvelope'],
                        ],
                    ],
                ],
                'Unauthenticated' => $this->errorResponse('Authentication is required.'),
                'Forbidden' => $this->errorResponse('The authenticated user or selected context is not authorized.'),
                'ValidationError' => $this->errorResponse('The request failed validation.'),
                'RateLimited' => $this->errorResponse('The API rate limit was exceeded.'),
            ],
            'headers' => [
                'RequestId' => [
                    'description' => 'Request correlation identifier.',
                    'schema' => ['type' => 'string'],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function errorResponse(string $description): array
    {
        return [
            'description' => $description,
            'headers' => [
                'X-Request-ID' => ['$ref' => '#/components/headers/RequestId'],
            ],
            'content' => [
                'application/json' => [
                    'schema' => ['$ref' => '#/components/schemas/ErrorEnvelope'],
                ],
            ],
        ];
    }

    /**
     * @param  list<Route>  $routes
     */
    private function routeInventory(array $routes): string
    {
        $lines = [
            '# CoFund API v1 route inventory',
            '',
            '> Generated by `php artisan api:documentation`. Do not edit by hand.',
            '',
            sprintf('Total routes: **%d**.', count($routes)),
            '',
            '| Domain | Methods | URI | Route name | Authentication | Tenant | Role |',
            '| --- | --- | --- | --- | --- | --- | --- |',
        ];

        foreach ($routes as $route) {
            $middleware = array_values(array_filter(
                $route->gatherMiddleware(),
                is_string(...),
            ));
            $lines[] = sprintf(
                '| %s | `%s` | `/%s` | `%s` | %s | %s | %s |',
                $this->domainFor($route),
                implode('|', $this->documentedMethods($route)),
                $route->uri(),
                $route->getName() ?? '—',
                $this->yesNo($this->hasMiddleware(
                    $middleware,
                    'auth',
                    'Illuminate\Auth\Middleware\Authenticate',
                )),
                $this->yesNo($this->hasMiddleware(
                    $middleware,
                    'tenant.context',
                    ResolveTenantContext::class,
                )),
                $this->yesNo($this->hasMiddleware(
                    $middleware,
                    'role.context',
                    ResolveRoleContext::class,
                )),
            );
        }

        return implode(PHP_EOL, $lines).PHP_EOL;
    }

    /**
     * @return list<string>
     */
    private function documentedMethods(Route $route): array
    {
        return array_values(array_filter(
            $route->methods(),
            fn (string $method): bool => $method !== 'HEAD',
        ));
    }

    private function domainFor(Route $route): string
    {
        $segment = Str::of($route->uri())
            ->after('api/v1/')
            ->before('/')
            ->toString();

        return match (true) {
            in_array($segment, ['health', 'public'], true) => 'Public',
            in_array($segment, ['auth', 'context', 'roles', 'role-assignments'], true) => 'Identity',
            in_array($segment, ['dashboard', 'dashboards', 'branches', 'saved-reports', 'report-exports'], true),
            Str::startsWith($segment, 'reports') => 'Reports',
            in_array($segment, ['members', 'groups'], true) => 'Membership',
            Str::startsWith($segment, 'contribution') => 'Contributions',
            in_array($segment, ['wallets', 'member-wallets'], true) => 'Wallets',
            in_array($segment, ['ledger-accounts', 'financial-transactions', 'accounting'], true) => 'Accounting',
            Str::startsWith($segment, 'loan') => 'Loans',
            Str::startsWith($segment, 'welfare') => 'Welfare',
            Str::startsWith($segment, 'expense') || Str::startsWith($segment, 'petty-cash') => 'Expenses',
            Str::startsWith($segment, 'collector') || Str::startsWith($segment, 'cash-') => 'Reconciliation',
            Str::startsWith($segment, 'withdraw') || $segment === 'savings-holds' => 'Withdrawals',
            Str::startsWith($segment, 'notification') => 'Notifications',
            Str::startsWith($segment, 'receipt') => 'Receipts',
            default => Str::headline($segment),
        };
    }

    private function yesNo(bool $value): string
    {
        return $value ? 'Yes' : 'No';
    }

    /**
     * @param  list<string>  $middleware
     */
    private function hasMiddleware(array $middleware, string $alias, string $class): bool
    {
        return in_array($alias, $middleware, true)
            || in_array($class, $middleware, true);
    }
}
