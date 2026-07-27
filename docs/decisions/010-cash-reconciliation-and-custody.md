# ADR 010: Cash reconciliation and custody

- Status: Accepted for foundation implementation
- Date: 2026-07-25

## Context

CoFund must distinguish recorded collector cash, physically declared cash,
cash accepted by another officer, and cash deposited externally. A mutable
balance would hide custody, variances, and maker-checker responsibility.

## Decision

Each collector-cash payment is linked at posting time to that collector
wallet's one open session. Closing snapshots ledger-derived expected cash and
prevents later attachment. Declarations are append-only; corrections supersede
an earlier declaration before approval.

Cash moves between custody wallets only when an independently received
handover is approved. Shortages remain in collector custody and create a
variance. Overages credit a dedicated unidentified-cash liability. Office-cash
deposits require a different confirmer and post to bank or Mobile Money.
Corrections use linked compensating transactions.

All records use tenant ownership, public UUIDs, minor units, verified active
context, actor metadata, and explicit lifecycle states.

## Consequences

- Custody is visible at collector, office, and external-destination stages.
- Snapshot totals are rebuildable; ledger balances remain authoritative.
- Shortages require repayment, suspense resolution, or authorized write-off.
- Mobile Money is manual reconciliation only; integration remains deferred.

## Owner review

The accounting owner must approve the overage-liability and shortage-expense
accounts, write-off thresholds, evidence requirements, and approval thresholds.
