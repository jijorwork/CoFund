# ADR 014: Savings withdrawals

## Status

Accepted for Phase 11.

## Decision

Withdrawable savings are calculated from the member savings wallet's posted
ledger entries. Contribution obligations and editable snapshots are never
treated as savings. Submitted requests reserve their gross requested amount
through a non-accounting `pending_withdrawal` savings hold.

The request amount is the gross reduction in member savings. Fees are deducted
from payout and recognised only when money moves. A payout debits member
savings liability for the gross amount, credits the destination asset for the
net amount, and credits withdrawal fee income for the difference.

Loan, guarantee, welfare, contribution, hold, minimum-balance, product, and
reason restrictions are recalculated at workflow boundaries. Explicit
security holds are not inferred from arbitrary original principal. Partial
payout fees are allocated proportionally and the final payout absorbs the
rounding residual.

Posted payouts and receipts are immutable. Reversal creates a compensating
financial transaction, restores the approved outstanding amount, and recreates
the reservation where the request remains active.

## Consequences

- Approval never moves money.
- Concurrent requests are constrained through wallet/request/hold locking.
- Full-balance requests require secondary approval.
- Member exit and debt set-off remain separate, deferred workflows.
- MySQL concurrency behaviour requires production-engine verification.
