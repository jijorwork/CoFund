# ADR 013: Welfare claims and accounting

## Status

Accepted for Phase 10.

## Decision

CoFund evaluates welfare eligibility when a claim is submitted and again at
final approval. The result is an explainable snapshot, not a permanently
authoritative balance. Waiting periods use calendar addition without month-end
overflow. Benefits use integer minor units and basis-point arithmetic with
deterministic truncation before configured minimum and maximum limits.

Final approval recognises an accrual: welfare benefit expense is debited and
welfare claims payable is credited. Payments subsequently debit that payable
and credit an active asset wallet. Products may allow partial payments, but the
sum of active payments cannot exceed the recognised liability.

Beneficiaries are claim-specific snapshots. Their active basis-point
allocations cannot exceed 10,000 and allocated amounts cannot exceed the
approved benefit. Changes after approval require an append-only amendment.
Sensitive identifiers are encrypted and masked.

Payment reversals restore the outstanding payable through compensating ledger
transactions. A liability can be reversed only when it has no active payments.
Original claims, approvals, liabilities, payments, receipts, and financial
transactions remain historical.

## Consequences

- Approval and treasury payment remain separate capabilities.
- Role switching cannot bypass maker-checker identity rules.
- Approved claims remain payable if a product is later suspended or closed.
- MySQL concurrency and check-constraint behaviour requires production-engine
  verification beyond SQLite tests.
