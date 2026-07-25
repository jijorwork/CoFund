# API Conventions

## Surface and authentication

- All first-party business endpoints are rooted at `/api/v1`; route names use `api.v1.*`.
- JSON is the only representation unless a dedicated export endpoint documents another type.
- The eventual API authentication mechanism is undecided. Existing Fortify authentication is session-based; token authentication must be selected explicitly before API implementation.
- Tenant context is selected through a documented context endpoint or context headers containing public identifiers, then verified against active assignments. Body/query `organisation_id`, `branch_id`, `role_id`, or `group_id` values never establish authority.

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

## Code naming

- Controllers are singular (`ContributionController`) and thin.
- Form Requests are intent-based (`StoreContributionRequest`, `ReverseJournalRequest`).
- API Resources are singular (`ContributionResource`) with collection resources only when collection metadata differs.
- Policies are singular model names (`ExpensePolicy`).
- Actions use imperative intent (`RecordContribution`, `PostJournal`, `ReverseTransaction`); services name cohesive capabilities (`LedgerPostingService`) and are not generic repositories.

