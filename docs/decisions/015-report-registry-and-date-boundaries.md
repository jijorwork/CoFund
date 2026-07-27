# ADR 015: Report registry and date boundaries

## Status

Accepted for Phase 12.

## Decision

CoFund uses a typed in-process report registry. Each public report key defines
its permission, filters, sorts, grouping, columns, export formats, and
synchronous limits. API clients cannot name classes, tables, SQL, or arbitrary
columns. Saved definitions must pass current authorization when executed.

Every execution creates one `ReportDateRange` in the organisation timezone.
`date_from` and `date_to` are inclusive local dates; `as_of` resolves to the
end of that local day. Future dates are rejected and synchronous execution is
limited to 366 days.

Financial reports remain ledger-authoritative. Sections use immutable account
categories and purposes. Ratios use integer basis points and return `null`
when the denominator is zero. Initial reporting is uncached, and queued private
CSV is the only enabled export format.

## Consequences

Direct, saved, and exported reports share the same tenant and role boundary.
Metadata cannot mutate or replace the ledger. Adding a report requires an
explicit registry entry and query service. XLSX/PDF and caching need later
decisions.
