<?php

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;

/**
 * @return list<Route>
 */
function cofundApiRoutes(): array
{
    return array_values(array_filter(
        RouteFacade::getRoutes()->getRoutes(),
        fn (Route $route): bool => str_starts_with($route->uri(), 'api/v1/'),
    ));
}

test('api responses expose correlation and security headers', function () {
    $this->getJson('/api/v1/health', ['X-Request-ID' => 'client-request-123'])
        ->assertSuccessful()
        ->assertHeader('X-Request-ID', 'client-request-123')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Referrer-Policy', 'no-referrer')
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('meta.request_id', 'client-request-123');
});

test('untrusted request identifiers are replaced', function () {
    $response = $this->getJson('/api/v1/health', [
        'X-Request-ID' => "invalid\nrequest",
    ])->assertSuccessful();

    expect($response->headers->get('X-Request-ID'))
        ->not->toBe("invalid\nrequest")
        ->toMatch('/^[0-9a-f-]{36}$/');
});

test('all api v1 routes use the named api rate limiter', function () {
    foreach (cofundApiRoutes() as $route) {
        expect($route->gatherMiddleware())
            ->toContain('throttle:api');
    }
});

test('private api routes cannot lose authentication or context middleware', function () {
    foreach (cofundApiRoutes() as $route) {
        $middleware = $route->gatherMiddleware();
        $uri = $route->uri();

        if (in_array($uri, [
            'api/v1/health',
            'api/v1/public/receipts/verify/{verificationCode}',
        ], true)) {
            continue;
        }

        expect($middleware)
            ->toContain('auth');

        if (str_starts_with($uri, 'api/v1/auth/')) {
            continue;
        }

        expect($middleware)->toContain('tenant.context');

        if ($uri !== 'api/v1/context') {
            expect($middleware)->toContain('role.context');
        }
    }
});

test('api routing errors use the standard error envelope', function () {
    $this->getJson('/api/v1/does-not-exist')
        ->assertNotFound()
        ->assertJsonPath('error.code', 'not_found')
        ->assertJsonStructure([
            'error' => ['code', 'message', 'details'],
            'meta' => ['request_id'],
        ]);

    $this->postJson('/api/v1/health')
        ->assertMethodNotAllowed()
        ->assertJsonPath('error.code', 'method_not_allowed');
});

test('generated OpenAPI contract covers every api v1 route and method', function () {
    $this->artisan('api:documentation')->assertSuccessful();

    /** @var array<string, mixed> $document */
    $document = json_decode(
        (string) file_get_contents(base_path('docs/openapi.json')),
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    expect($document['openapi'])->toBe('3.1.0');

    foreach (cofundApiRoutes() as $route) {
        $path = '/'.str_replace('api/v1/', '', $route->uri());

        foreach (array_diff($route->methods(), ['HEAD']) as $method) {
            expect($document['paths'][$path])
                ->toHaveKey(strtolower($method));
        }
    }
});
