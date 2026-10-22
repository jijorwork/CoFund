# Phase 15 API documentation and hardening

Phase 15 makes the current `/api/v1` surface discoverable and protects its
cross-cutting contracts. It does not introduce external token authentication or
change domain workflows.

## Generated contract

Run `php artisan api:documentation` to regenerate:

- `docs/openapi.json`, an OpenAPI 3.1 contract containing every API v1 route
  and HTTP method;
- `docs/api-route-inventory.md`, a table of each route's authentication,
  tenant-context, and role-context requirements.

These are generated artifacts. Route changes are incomplete until the command
has been rerun and its diff reviewed. The contract covers the common envelope,
session authentication, context headers, request IDs, idempotency header, and
standard errors. Domain-specific payload schemas remain incremental work.

## Runtime controls

- Every `/api/v1` route uses the named `api` limiter. The default is 120
  requests per minute per authenticated user or public IP. Deployments may set
  `API_RATE_LIMIT_PER_MINUTE`.
- `X-Request-ID` accepts 1–100 letters, digits, `.`, `_`, `:`, or `-`.
  Invalid values are replaced with a UUIDv7.
- Responses prevent MIME sniffing, framing, referrer disclosure, and storage in
  shared or browser caches.
- Unknown endpoints, unsupported methods, authentication, validation,
  authorization, and rate-limit failures use the CoFund error envelope.

## Authentication and tenant boundary

The v1 API remains a first-party Fortify session API with CSRF protection.
External bearer tokens remain deferred pending an approved token-context
design. Only health and public receipt verification are anonymous. Context
discovery and switching require authentication. Other private routes require
verified organisation context; business routes also require an active role.

## Deployment readiness

- Require HTTPS and secure session cookies.
- Configure only the actual trusted hosts and proxy ranges.
- Set a production API rate limit and use a shared limiter cache when scaled.
- Run `composer audit`, Pest, Pint, and PHPStan.
- Regenerate the API contract and review its diff.
- Verify workers, scheduler, private storage, backups, retention, and alerting.
- Remove seeded credentials and rotate default passwords.

## Owner decisions still required

- Third-party authentication, abilities, token rotation, and revocation.
- Production rate limits and stricter limits for sensitive commands.
- Deployment-specific trusted hosts and proxy ranges.
- Idempotency retention policy.
- OpenAPI publication and access policy.
