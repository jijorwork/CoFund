# Testing Strategy

## Phase 09 loan coverage

Loan tests cover deterministic terms and schedules, product limits, eligibility
snapshots and overrides, distinct approval actors, immutable offers, one active
disbursement, balanced posting, allocation order, arrears boundaries,
settlement, receipts, reversals, tenant isolation, UUID routes, permissions,
and idempotency conflicts. MySQL integration coverage is required for locking
and identifier-length behavior because SQLite cannot prove those properties.

## Expense coverage

Expense tests cover tenant ancestry, account classification, integer line
totals, approval-threshold snapshots, append-only decisions, protected routes,
and accounting regressions. Production-engine CI must add concurrent sequence,
approval, overpayment, and idempotency contention.

## Reconciliation coverage

Reconciliation tests must cover denomination arithmetic, exact/shortage/overage
classification, one-open-session constraints, immutable declarations, tenant
ancestry, lifecycle route registration, authentication, central-ledger
postings, maker-checker rejection, idempotent retries, branch isolation, and
compensating reversals. MySQL migration verification is required because
identifier limits and concurrent unique-key behavior are not fully represented
by the SQLite feature-test database.

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

The identity foundation specifically tests idempotent authorization seeding and
the role matrix; active, future, expired, suspended, and revoked assignments;
organisation and branch ancestry; stale session invalidation; active-role-only
permission checks; generic forbidden context errors; paginated scoped role
assignment APIs; and separation of duties by user identity.

Membership tests cover number allocation, lifecycle history, branch and tenant
scope, protected profile fields, account-link uniqueness, historical group
membership, beneficiary totals, nested-resource resolution, sensitive-field
masking, permission boundaries, private uploads, downloads, and verification.
Production-engine CI must additionally exercise concurrent number generation
and beneficiary allocation under real row locking.

Contribution coverage includes configuration and lifecycle validation;
deterministic daily, weekly, monthly, one-time, and flexible schedules; horizon
limits and rerun idempotency; enrolment eligibility and separation of duties;
position semantics; UUID-only responses; permissions; and tenant/branch scope.
Production-engine CI must prove concurrent schedule generation under real row
locking and unique-index contention.

Accounting coverage proves default-chart idempotency and classification;
wallet ownership, uniqueness, lifecycle, and derived balances; period overlap
and close/reopen behavior; balanced atomic posting; deterministic transaction
numbers; same-key retries and conflicting-key rejection; immutable entries and
posted transactions; exact compensating reversals; trial balance; public-ID-only
API resources; permission boundaries; and organisation/branch isolation.
Production-engine CI must additionally exercise concurrent sequence and
idempotency allocation under real row locks and validate the portable
application-level invariants against the selected engine.

Collection coverage includes one-transaction cash posting, source/destination
wallet accounting, allocation and unallocated totals, separated obligation
satisfaction, payment and receipt idempotency, immutable history, compensating
reversal, derived wallet restoration, public receipt privacy, UUID-only APIs,
authorization, and tenant-consistent factories. Production-engine CI must
prove concurrent payments cannot over-satisfy an obligation and that payment
and receipt sequences remain unique under contention.

## Database compatibility

SQLite remains fast for normal feature tests, but it cannot prove all production-engine constraints or locking behavior. Once the production database is approved, CI should add a targeted suite against that engine for transactions, row locks, indexes, constraints, JSON behavior, and concurrent idempotency.

## Delivery gate

Every change adds or updates a Pest test and runs the narrowest relevant `php artisan test --compact` target. PHP changes also run `vendor/bin/pint --dirty --format agent`; meaningful backend changes run Larastan. Before release, run the full suite, static analysis, dependency audits, and production-engine integration tests.
Phase 10 welfare coverage targets product configuration, calendar-safe waiting
periods, explainable eligibility, deterministic integer benefit calculations,
claim workflow separation, append-only approval history, liability and payment
double-entry postings, idempotency conflicts, compensating reversals, receipt
privacy, branch/organisation isolation, and aggregate reports. Financial tests
must assert both business state and balanced ledger entries. Production-engine
tests remain necessary for MySQL row locking and concurrent sequence/payment
contention.

Phase 11 coverage targets ledger-derived savings, holds, restriction
explanations, integer fee arithmetic, reservation rollback, approval
separation, balanced payout entries, partial-fee reconciliation, idempotency,
reversals, receipt privacy, tenant isolation, and reports. Production-engine
tests remain required for concurrent submission and payout contention.
## Phase 12 coverage

Reporting tests target registry uniqueness and allow lists, local date
boundaries, integer ratios, ageing, tenant/branch isolation, ledger ordering,
normal balances, trial-balance equality, account-purpose classification,
saved-definition permission revalidation, private exports, and bounded
dashboard queries. Production MySQL testing must verify JSON fields, aggregate
query plans, indexes, and queued private-file storage.
# Notification verification

Phase 13 adds focused unit coverage for event registration, allow-listed
placeholder rendering, output escaping, and trusted action URLs. Feature
coverage should exercise dispatch deduplication, after-commit job creation,
tenant isolation, preference inheritance, mandatory-event behaviour,
append-only attempts, idempotent in-app delivery, email failure sanitisation,
read/unread/archive endpoints, summary aggregates, and disabled provider
adapters. Queue tests must use fakes and must never contact real providers.
