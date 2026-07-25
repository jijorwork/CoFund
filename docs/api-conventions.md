# API Conventions

## Surface and authentication

- All first-party business endpoints are rooted at `/api/v1`; route names use `api.v1.*`.
- JSON is the only representation unless a dedicated export endpoint documents another type.
- Current first-party API authentication uses Fortify's `web` session guard and CSRF protection. Token authentication for external clients remains undecided.
- `GET /api/v1/auth/contexts` lists valid choices and `POST /api/v1/auth/context` selects exact organisation, optional branch, and assignment UUIDs. Body/query numeric ownership IDs never establish authority.
- Staff routes run `web`, `auth`, tenant resolution, then role resolution. Health has no context middleware; context list/switch require authentication but no preselected role.

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

## Contribution API

Plan routes live under `/api/v1/contribution-plans`; enrolments and their
read-only schedules live under `/api/v1/contribution-enrolments`. Lifecycle
changes use explicit action endpoints. Lists accept documented `filter[...]`
values and allow-listed sorts, paginate at 25, and resolve UUIDs only within
verified tenant and branch context.

Amounts use integer `*_minor` values; plan resources also render decimal strings
without floating-point arithmetic. Enrolment positions are expected-contribution
summaries, not wallet balances. Clients cannot create obligations directly.

## Code naming

- Controllers are singular (`ContributionController`) and thin.
- Form Requests are intent-based (`StoreContributionRequest`, `ReverseJournalRequest`).
- API Resources are singular (`ContributionResource`) with collection resources only when collection metadata differs.
- Policies are singular model names (`ExpensePolicy`).
- Actions use imperative intent (`RecordContribution`, `PostJournal`, `ReverseTransaction`); services name cohesive capabilities (`LedgerPostingService`) and are not generic repositories.
