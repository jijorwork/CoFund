# CoFund Architecture

## Baseline repository

At 2026-07-25 the repository runs Laravel Framework 13.22.0. `composer.json` requires PHP `^8.3` (the inspected CLI is PHP 8.4.23). It is a Laravel server-rendered starter using Livewire 4.3, Flux UI 2.15, Tailwind CSS 4, Vite 8, and Blade. Fortify provides session authentication, registration, password reset, email verification, confirmed two-factor authentication, and passkeys. Pest 4 and PHPUnit 12 are configured; feature tests use `RefreshDatabase` with SQLite, array cache/session, and the synchronous queue.

The organisation foundation now adds `/api/v1/health`, an authenticated
`/api/v1/context` diagnostic endpoint, organisations, branches, typed
organisation settings, and the temporary role-free `organisation_users` access
link. API authentication still uses the existing `web` session guard; there is
no token authentication, authorization-role package, or administration
framework.

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
| `Loans` | Loans, guarantors, schedules, repayments (post-MVP) |
| `Welfare` | Welfare schemes, claims, benefits |
| `Expenses` | Expenses, petty cash, disbursement requests |
| `Reconciliation` | Statements, matching, variances, close workflow |
| `Approvals` | Reusable approval definitions, requests, steps, decisions |
| `Complaints` | Cases, correspondence, resolution tracking |
| `Payments` | Payment intents and provider adapters; Mobile Money is post-MVP |
| `Auditing` | Immutable actor/action/change metadata and access trails |

Use normal Laravel locations and domain subdirectories where valuable, for example `app/Domain/Accounting/Actions`, `Models`, `Enums`, `Policies`, and `Services`. HTTP adapters remain in `app/Http`; API Resources and Form Requests call domain actions. Extract a service only for a cohesive capability; use an action for a discrete use case. External payment providers sit behind contracts.

## Cross-cutting request flow

1. Authentication identifies the global user.
2. Context middleware resolves the requested public selectors and verifies access. During the organisation foundation it checks an active `organisation_users` record; the next phase replaces this authorization decision with an active role assignment.
3. The verified context is stored in a request-scoped immutable object and Laravel request context/log metadata.
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
