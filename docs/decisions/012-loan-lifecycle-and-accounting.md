# ADR 012: Loan lifecycle and accounting

## Status

Accepted for Phase 09.

## Decision

CoFund keeps applications, eligibility, guarantees, collateral, approvals,
offers, loans, schedules, disbursements, repayments, and allocations inside the
Loans domain of the modular monolith.

Applications and approvals do not post accounting. The accepted offer is an
immutable terms snapshot. A loan is created in `pending_disbursement`, with a
deterministic schedule, and accounting starts only through
`DisburseApprovedLoan`.

Interest, repayment fees, and penalties are recognised when cash is received.
Processing fees are recognised once at disbursement. Deducted fees reduce cash
paid; financed fees increase the member loan receivable. The member loan wallet
is an asset-backed view of the loan-receivable account and has no balance
column.

Repayments allocate oldest due amounts in penalty, fee, interest, and principal
order. One repayment creates one balanced transaction. Position and arrears
snapshots remain rebuildable from active schedules, allocations, posted
transactions, and compensating reversals.

## Consequences

- Product terms used by disbursed loans cannot be changed retrospectively.
- Role switching cannot bypass approval separation because actor identity is
  checked across the append-only history.
- Reversals preserve applications, offers, schedules, allocations, and original
  accounting records.
- MySQL concurrency behaviour still requires production-engine verification;
  SQLite tests cannot prove row-lock semantics.
