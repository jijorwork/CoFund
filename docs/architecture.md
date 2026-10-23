# CoFund Architecture

## Expense boundary

Expenses owns classification, requests, approval evidence, payments,
petty-cash advances, and retirements. Approval never posts. Financial effects
go through Accounting and custody balances remain Wallet-derived. Procurement,
payroll, suppliers, inventory, loans, welfare, and provider integrations remain
outside this boundary.

## Reconciliation boundary

Reconciliation owns collector sessions, immutable cash declarations, custody
handovers, variances, and office-cash deposit batches. It consumes Collections
for session-linked payment facts, Wallets for custody identities, Accounting as
the only balance/posting authority, and Identity/Approvals for permissions and
maker-checker controls. It does not duplicate payments, transaction entries,
wallet balances, receipts, or member data.

## Baseline repository

At 2026-07-25 the repository runs Laravel Framework 13.22.0. `composer.json` requires PHP `^8.3` (the inspected CLI is PHP 8.4.23). It is a Laravel server-rendered starter using Livewire 4.3, Flux UI 2.15, Tailwind CSS 4, Vite 8, and Blade. Fortify provides session authentication, registration, password reset, email verification, confirmed two-factor authentication, and passkeys. Pest 4 and PHPUnit 12 are configured; feature tests use `RefreshDatabase` with SQLite, array cache/session, and the synchronous queue.

The foundation exposes `/api/v1/health`, an authenticated tenant diagnostic,
context selection, assignable roles, and role-assignment administration.
Organisations, branches, typed settings, and `organisation_users` access are
implemented. API authentication uses the existing `web` session guard. Spatie
Laravel Permission 8 defines global system roles, permissions, and their matrix;
CoFund's `organisation_user_roles` table exclusively defines where a user may
act. Token authentication and an administration framework are not installed.

## Architectural style

Use a modular monolith: one Laravel application, one deployment unit, and one transactional relational database. Domains live under coherent namespaces within the existing Laravel structure, share Laravel infrastructure deliberately, and communicate through typed actions, contracts at true external boundaries, events, and stable identifiers. Do not turn each module into a Composer package.

Suggested namespaces (introduced only as implementation demands):

| Domain | Responsibility |
| --- | --- |
| `Identity` | Users, authentication linkage, roles, permissions, role assignments, active context |
| `Organisations` | Organisations, branches, groups, organisation settings |
| `Membership` | People/member records, organisation memberships, beneficiaries |
| `Contributions` | Plans, enrolments, schedules, collections, receipts |
| `Wallets` | Member/organisation wallet identities and balance projections |
| `Accounting` | Chart of accounts, journals, entries, posting and reversals |
| `Loans` | Phase 09 products, applications, guarantees, collateral, approvals, offers, schedules, disbursements, repayments, settlement, and reporting |
| `Welfare` | Welfare schemes, claims, benefits |

Phase 10 keeps welfare products, enrolment and claim workflow in the Welfare
domain. Eligibility and benefit calculations are explainable domain services.
Final approval creates a claims-payable liability through Accounting; benefit
payment settles that liability from an asset-backed wallet. Documents use a
dedicated private store, while receipts reuse the Collections receipt
infrastructure.
| `Expenses` | Expenses, petty cash, disbursement requests |
| `Reconciliation` | Statements, matching, variances, close workflow |
| `Approvals` | Reusable approval definitions, requests, steps, decisions |
| `Complaints` | Cases, correspondence, resolution tracking |
| `Payments` | Payment intents and provider adapters; Mobile Money is post-MVP |
| `Auditing` | Immutable actor/action/change metadata and access trails |

Use normal Laravel locations and domain subdirectories where valuable, for example `app/Domain/Accounting/Actions`, `Models`, `Enums`, `Policies`, and `Services`. HTTP adapters remain in `app/Http`; API Resources and Form Requests call domain actions. Extract a service only for a cohesive capability; use an action for a discrete use case. External payment providers sit behind contracts.

## Cross-cutting request flow

1. Authentication identifies the global user.
2. Tenant middleware resolves public selectors from the selected server-side session context (or explicit tenant headers for the diagnostic route) and verifies active organisation access.
3. Role middleware reloads the selected assignment, verifies user, tenant, branch, status and dates, then stores the verified role in the scoped context and Laravel log context.
4. Authorization policy checks the capability and resource ownership.
5. A Form Request validates business input; it cannot supply authoritative organisation ownership.
6. A domain action executes in a transaction, writes audit metadata, and posts ledger effects when applicable.
7. An API Resource returns the versioned representation.

Queue jobs must carry verified organisation/context identifiers explicitly and revalidate invariants where state may have changed. Never derive tenant context from mutable global session state inside a queued job.

## Phased implementation order

1. Project foundation: API routing/error envelope, public IDs, context primitives, CI and architecture tests.
2. Organisations and branches.
3. Roles, permissions, and active context.
4. Members and beneficiaries.
5. Contribution plans and enrolments.
6. Accounting ledger and wallets.
7. Contribution collection.
8. Expenses and reconciliation.
9. Approval workflows and auditing.
10. Filament administration, subject to package approval.
11. API documentation and hardening.

Deferred modules follow only after MVP controls and ledger reconciliation are proven.

## Organisation foundation implementation

Organisation code is globally unique; branch code is unique per organisation.
Externally addressable organisations and branches use UUIDv7 `public_id` values.
`TenantContext` is scoped in Laravel's container, holds one active organisation
and an optional same-organisation branch, and is cleared after tenant-aware HTTP
requests. `ResolveTenantContext` accepts only `X-Organisation-ID` and optional
`X-Branch-ID` public UUIDs, returns a generic forbidden response for every
invalid or inaccessible selector, and is not global middleware.

Organisation-domain code lives under `app/Domain/Organisations`; HTTP middleware,
resources, and response formatting remain in `app/Http`.

## Identity authorization foundation

System role names and permission names are stable machine values. Labels are
resolved by `SystemRole`; roles are not duplicated per organisation.
`organisation_user_roles` records assignment history and uses a nullable unique
active-scope key to prevent an exact active duplicate while allowing revoked
history. Assignment and revocation actions are transactional and actor-stamped.
Authorization checks permissions on the one verified active role only.

Role definitions are treated as seeded system catalogue entries. The package's
standard polymorphic tables exist for package lifecycle compatibility but are
not an authorization source, and the `User` model does not use Spatie's role
trait. CoFund exposes no direct model-role or model-permission assignment API.

## Membership foundation

Membership owns organisation-specific member identity, lifecycle, groups,
contacts, private documents, and optional linkage to a global user. A member is
never created merely because a user exists, and linking does not assign a role.
All membership queries begin with verified organisation context and apply the
active assignment's branch restriction. Organisation-wide roles may access all
branches; branch-scoped roles cannot access branchless or other-branch records.

Membership numbers use a per-organisation, per-year row-locked counter and the
format `{ORGCODE}-{YYYY}-{000001}`. Status transitions are explicit actions and
append immutable history. Beneficiaries, next of kin, and emergency contacts
share one typed model while remaining independent roles. Documents use a
restricted morph map and Laravel's private local disk; APIs never expose paths.
Member and contact national-identification values are encrypted at rest and
masked in API representations.

## Contribution-plan foundation

Contributions owns plan configuration, enrolment, deterministic expected
schedules, and read-only position calculations. Plans may be organisation-wide,
branch-specific, or group-specific. Branch roles see organisation-wide plans
plus plans for their active branch; enrolments remain exactly branch scoped.

Schedule-critical configuration is locked once pending, active, or suspended
enrolments exist. Active enrolments generate bounded obligations under a row
lock; reruns identify records by enrolment and due date. Approval-required
enrolments have no schedule until a different actor approves them. Obligations
are expectations, not financial transactions, and create no balance or posting.

The Collections subdomain owns manual contribution payments, obligation
allocations, and digital receipts. It calls Accounting once per payment and
never creates ledger entries directly. Allocation changes satisfaction only
after that posting succeeds. Source-wallet resolution belongs to Collections;
external provider intents and callbacks remain in the deferred Payments domain.

## Savings withdrawals

Withdrawals owns savings availability, holds, requests, approvals, payouts,
and reversals. It consumes ledger-derived wallet balances and authoritative
loan/welfare records without taking ownership of them. Only payout delegates
financial movement to Accounting.
## Reports and dashboards

`app/Domain/Reports` is a read-only layer over existing domain models and the
ledger. A typed registry allow-lists report keys, filters, sorting, grouping,
columns, permissions, limits, and export formats. Dashboard builders return
bounded summaries. Financial statements use ledger account category, purpose,
and normal balance; saved definitions and run/export metadata are never
accounting sources.

Phase 12 reporting is uncached. Each execution derives one organisation-local
date range and reapplies tenant, branch, and active-role scope. CSV generation
uses the existing queue and private local storage. XLSX, PDF, snapshots,
cross-organisation consolidation, external BI, and chart rendering are
deferred.

## Notifications

`app/Domain/Notifications` separates a tenant-owned dispatch from its
channel-specific deliveries and append-only delivery attempts. A stable event
registry defines category, priority, mandatory status, digest eligibility,
allowed channels, recipient resolver, and required placeholders. Business
operations must publish only after their transaction commits; delivery failure
never rolls back the completed business operation.

Every recipient dispatch has an organisation-scoped deterministic
deduplication key. Message text and policy are snapshotted at dispatch time.
Templates are versioned, active versions are immutable, and rendering accepts
only allow-listed scalar placeholders. Action URLs must be relative or use the
configured application host. In-app delivery is implemented; email uses the
configured Laravel mail transport. SMS and messaging are provider-neutral and
remain unavailable through null providers until an approved provider is
configured. Digest tables are foundations for later scheduled processing.

## Filament administration

CoFund uses one Filament panel at `/admin`. It does not use Filament native
tenancy because organisation, branch, and acting-role context already belong to
the domain architecture. Persistent panel middleware verifies the existing
session context on the initial request and every Livewire update.

Panel resources begin with the active organisation and apply the active branch
where the record is branch-owned. Navigation visibility and direct resource
access use `RoleAuthorization`; hiding navigation is never the only control.
Posted financial records and notification history are read-only. Dashboard
widgets delegate metrics to Phase 12 report services.

## API contract and hardening

The registered Laravel route collection is the source for
`docs/openapi.json` and `docs/api-route-inventory.md`; both are regenerated by
`php artisan api:documentation`. Automated tests enforce authentication,
organisation context, and role context by route category. API v1 uses one named
per-user/per-IP limiter, validated request correlation identifiers, standard
JSON routing errors, and defensive response headers. Organisation administrators
can issue 1–90 day, read-only Sanctum tokens for their current organisation,
branch, and role assignment through the session-protected token API. The raw
token is returned only at creation. `/api/v1/external` verifies the stored
context and current role access on every request; context headers cannot switch
it. The external surface currently exposes context, dashboard, and member
list/detail. Broader external operations remain pending.
