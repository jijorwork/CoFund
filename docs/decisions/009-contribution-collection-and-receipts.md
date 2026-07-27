# ADR 009: Contribution Collection, Allocation, and Receipts

- Status: Accepted
- Date: 2026-07-25

## Context

Contribution obligations describe expected amounts but do not prove receipt of
money. CoFund needs manual collection before provider integrations and
reconciliation.

## Decision

Record each contribution payment as one immutable business record backed by one
balanced Accounting transaction. Debit the authorised source asset wallet and
credit the member savings liability wallet. Allocate the payment separately
across generated obligations; never post one transaction per allocation.

Keep obligation timing (`schedule_status`) separate from satisfaction
(`satisfaction_status`). Allocation supports oldest-due-first,
current-period-first, selected obligations, and explicitly unallocated money.
Partial, advance, and excess allocation obey plan flags.

Payments, allocations, and receipts are immutable. Full correction uses linked
financial, allocation, payment, and receipt reversals. Payment and receipt
numbers use locked organisation/year sequences. Public receipt verification
uses an unguessable code and SHA-256 hash and exposes a minimal, rate-limited
authenticity view.

## Consequences

- Wallet balance and contribution satisfaction remain distinct.
- Provider callbacks, reconciliation, PDF rendering, notifications, and
  post-payment reallocation are deferred.
- Receipt reissue returns the same immutable receipt and creates no new
  transaction or receipt number.
- Production-engine concurrency tests remain required.
