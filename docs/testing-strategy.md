# Testing Strategy

## Current baseline

The project uses Pest 4 with PHPUnit 12. Feature tests extend Laravel's test case and apply `RefreshDatabase`; the configured test database is SQLite with array cache/session and synchronous queues. Existing tests cover starter authentication, dashboard, and settings flows. Larastan and Pint are installed for static analysis and formatting.

## Test layers

- **Feature/API tests:** primary coverage for endpoints, middleware, validation, policies, transactions, resources, and response envelopes.
- **Domain/unit tests:** focused coverage for pure posting rules, money calculations, state transitions, and context rules.
- **Architecture tests:** prohibit cross-domain coupling that bypasses public actions/contracts, direct wallet balance mutation, tenant-owned models without ownership, and exposed internal IDs.
- **Integration tests:** database constraints, queues, files, mail/notifications, provider adapters with fakes, and projection rebuilding.
- **Browser tests:** later, only for critical mobile and administration workflows.

## Mandatory scenarios

- Cross-organisation, cross-branch, and stale/revoked-context attempts are denied.
- Role switching changes capabilities without changing the authenticated user.
- Factories create valid tenant ancestry and offer named states for roles/statuses.
- Every posting is balanced per currency, atomic, idempotent, and traceable to its source.
- Retrying creates one financial effect; conflicting idempotency payloads fail.
- Posted/completed records cannot be edited or deleted; reversal creates exact compensating lines.
- Approval segregation, thresholds, rejection, expiry, and audit metadata are covered.
- Wallet projections can be rebuilt and reconcile exactly to the ledger.
- APIs maintain status codes, error shape, pagination, filtering, and public-ID-only output.

## Database compatibility

SQLite remains fast for normal feature tests, but it cannot prove all production-engine constraints or locking behavior. Once the production database is approved, CI should add a targeted suite against that engine for transactions, row locks, indexes, constraints, JSON behavior, and concurrent idempotency.

## Delivery gate

Every change adds or updates a Pest test and runs the narrowest relevant `php artisan test --compact` target. PHP changes also run `vendor/bin/pint --dirty --format agent`; meaningful backend changes run Larastan. Before release, run the full suite, static analysis, dependency audits, and production-engine integration tests.

