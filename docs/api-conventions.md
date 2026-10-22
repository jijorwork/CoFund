# API Conventions

## Expense APIs

Categories, requests, evidence, payments, advances, retirements, and reports
live under `/api/v1`. Financial commands require `Idempotency-Key`. Resources
expose UUIDs and minor units, never internal IDs, raw paths, fingerprints, or
ledger-account IDs.

## Reconciliation endpoints

The authenticated `/api/v1` surface includes collector sessions, cash
handovers, cash variances, cash deposits, and collection reports. Lifecycle
commands use explicit actions such as `close`, `declare-cash`, `submit`,
`receive`, `approve`, `confirm`, `reject`, `resolve`, `write-off`, and
`reverse`; clients cannot patch financial status fields directly.

Creating a handover or deposit, reversing either, and writing off a variance
requires an `Idempotency-Key` header. Reuse with another payload is rejected.
Responses expose public UUIDs and minor-unit amounts, never internal IDs,
fingerprints, polymorphic classes, or ledger-account IDs.

Reports are available under `/api/v1/reports/{collector-collections,cash-handovers,cash-variances,cash-deposits,cash-position}`.
They accept `date_from` and `date_to` where applicable, enforce verified tenant
and branch scope, and derive wallet positions from posted ledger entries.

## Surface and authentication

- All first-party business endpoints are rooted at `/api/v1`; route names use `api.v1.*`.
- `php artisan api:documentation` regenerates the authoritative route inventory and OpenAPI 3.1 contract from Laravel's registered routes.
- JSON is the only representation unless a dedicated export endpoint documents another type.
- Current first-party API authentication uses Fortify's `web` session guard and CSRF protection. Token authentication for external clients remains undecided.
- `GET /api/v1/auth/contexts` lists valid choices and `POST /api/v1/auth/context` selects exact organisation, optional branch, and assignment UUIDs. Body/query numeric ownership IDs never establish authority.
- Staff routes run `web`, `auth`, tenant resolution, then role resolution. Health has no context middleware; context list/switch require authentication but no preselected role.
- Every v1 route uses the named `api` rate limiter: 120 requests per minute by default, keyed by authenticated user or public IP.

## Responses

Success responses use Laravel API Resources and this stable shape:

```json
{
  "data": {},
  "meta": { "request_id": "..." }
}
```

Collections add `links` and pagination metadata. Creation returns `201`; successful commands without a representation return `204`; asynchronous work returns `202`. Expose public IDs and ISO-8601 UTC timestamps.

Errors use a consistent object:

```json
{
  "error": {
    "code": "validation_failed",
    "message": "The given data was invalid.",
    "details": { "amount_minor": ["The amount must be greater than zero."] }
  },
  "meta": { "request_id": "..." }
}
```

Use `401` for unauthenticated, `403` for authenticated but unauthorized/context-invalid, `404` where a scoped resource is absent, `409` for state/idempotency conflict, `422` for validation, and `429` for throttling. Messages are safe for clients; diagnostics remain in logs.

Client-supplied `X-Request-ID` values are limited to 100 safe correlation
characters; invalid values are replaced. Responses echo the accepted request
ID and disable MIME sniffing, framing, referrer disclosure, and storage in
shared or browser caches.

## Listing conventions

- Cursor pagination is preferred for high-volume ledgers and transactions; page pagination is acceptable for bounded administration lists. Default and maximum sizes should be configured centrally (proposed 25 and 100; owner approval required).
- Filters use `filter[field]=value`; sorting uses `sort=field,-created_at`; sparse fields or includes are introduced only per documented resource.
- Whitelist every filter, sort, and include. Default ordering is deterministic, usually newest public record first.
- Date filters use ISO-8601 dates/timestamps with explicit inclusive/exclusive semantics.

## Writes and idempotency

- Require `Idempotency-Key` on financial creates and other retry-sensitive commands. Scope it to authenticated user, active organisation, HTTP method, and route; store a request fingerprint and the completed response reference.
- Reuse with the same fingerprint returns the original result; reuse with different input returns `409`. Keys have a documented retention period (proposal: at least 24 hours; owner approval required).
- Use optimistic state checks or explicit workflow commands (`approve`, `post`, `reverse`) rather than generic status updates.
- Sensitive mutations require authorization, audit metadata, and possibly approval before execution.

## Membership API

Membership endpoints use public UUID route parameters and live under
`/api/v1/members` and `/api/v1/groups`. Nested contact and document resolution
always starts from the scoped member. Lists use validated `filter[...]` values,
an allow-listed `sort`, deterministic pagination of 25, and branch restrictions.
Lifecycle changes use action endpoints instead of generic status updates.

Member resources omit internal IDs and notes. National identifiers are masked
when returned. Document resources expose metadata only; downloads are
authorization-gated streams from private storage.

## Code naming

## Loan commands

Loan disbursement, repayment, settlement payment, repayment reversal, and
disbursement reversal require `Idempotency-Key`. Loan resources expose public
UUIDs only. Application, product, loan, and repayment lists use the standard
paginator and tenant/branch filters.

## Contribution API

Plan routes live under `/api/v1/contribution-plans`; enrolments and their
read-only schedules live under `/api/v1/contribution-enrolments`. Lifecycle
changes use explicit action endpoints. Lists accept documented `filter[...]`
values and allow-listed sorts, paginate at 25, and resolve UUIDs only within
verified tenant and branch context.

Amounts use integer `*_minor` values; plan resources also render decimal strings
without floating-point arithmetic. Enrolment positions are expected-contribution
summaries, not wallet balances. Clients cannot create obligations directly.

## Accounting and wallet API

Ledger accounts, wallets, transactions, trial balances, and member wallet
summaries live under `/api/v1`. Routes resolve public UUIDs within verified
active context; internal ownership keys are never accepted or returned.
Financial posting requires an `Idempotency-Key` header and currently permits
only controlled `adjustment` and `wallet_transfer` transaction types. Future
business modules must call the central posting action rather than exposing
generic entry creation.

Money inputs and authoritative values use positive integer minor units with an
ISO currency code. Transactions expose their lines only on detail responses.
Wallet balances and trial balances are computed from posted entries and must
not be interpreted as contribution obligations. Reversal is an explicit action
requiring a reason; generic status mutation and transaction deletion do not
exist.

## Contribution collection and receipts

Collection endpoints live under `/api/v1/contribution-payments`; creation and
reversal require `Idempotency-Key`. Clients provide public member, enrolment,
obligation, and source-wallet UUIDs, never ledger-account or internal IDs.
Money input uses integer `amount_minor`.

Allocation strategies are `oldest_due_first`, `current_period_first`,
`selected_obligations`, and `leave_unallocated`. Automatic partial allocation
is rejected when disabled. Advance allocation uses only generated obligations.
Excess is retained only when overpayments are permitted or the caller
explicitly leaves the payment unallocated.

Public receipt verification is rate limited and returns minimal authenticity
data with masked membership information. It excludes profiles, wallet/ledger
details, collector-private data, hashes, and internal identifiers.

## Code naming

- Controllers are singular (`ContributionController`) and thin.
- Form Requests are intent-based (`StoreContributionRequest`, `ReverseJournalRequest`).
- API Resources are singular (`ContributionResource`) with collection resources only when collection metadata differs.
- Policies are singular model names (`ExpensePolicy`).
- Actions use imperative intent (`RecordContribution`, `PostJournal`, `ReverseTransaction`); services name cohesive capabilities (`LedgerPostingService`) and are not generic repositories.
Welfare financial commands require `Idempotency-Key`. Welfare routes expose
public UUIDs only and resolve all records within the verified active
organisation and, where applicable, active branch. Private document storage
paths and sensitive beneficiary identifiers are never returned.

Withdrawal payout and reversal commands require `Idempotency-Key`. Clients
provide public wallet UUIDs and gross minor-unit amounts; ledger account IDs,
workflow state, approval actors, restriction internals, and fingerprints are
never accepted or exposed.
## Reporting API

Dashboards and consolidated reports use `/api/v1/dashboard`,
`/api/v1/dashboards/*`, and `/api/v1/reports/*`. Responses contain `data`,
`summary`, report/date/currency/scope metadata, and links. Local `date_from` and
`date_to` are inclusive; `as_of` means the organisation-local day end. Money
remains minor units. Ratios expose numerator, denominator, and basis points;
zero denominators return `null`.

Filters and sorts are registry allow-listed. Synchronous ranges are limited to
366 days. Saved-report permissions are rechecked when run. The initial export
format is private queued CSV for executable registry reports; APIs never expose
storage paths.

## Notifications API

Own-notification routes live under `/api/v1/notifications` and are scoped by
the verified organisation context and authenticated user. Clients can list,
view, mark read or unread, archive, and request an aggregate summary. Archiving
never deletes the dispatch or delivery history. Responses expose notification
UUIDs, safe rendered content, state, priority, and timestamps; they exclude
recipient contacts, template context, internal IDs, provider payloads, and
technical failure details.

`GET` and `PUT /api/v1/notification-preferences` manage the authenticated
user's organisation-specific optional channel preferences. Event keys must
exist in the registry. Mandatory event delivery is not disabled by a user
preference. SMS and messaging preferences may be recorded for future use but
do not make those channels available without a configured provider.
