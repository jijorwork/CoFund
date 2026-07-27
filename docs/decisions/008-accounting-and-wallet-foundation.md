# ADR 008: Central Accounting Posting and Derived Wallets

- Status: Accepted
- Date: 2026-07-25

## Context

CoFund needs auditable balances before contribution collection, expenses,
loans, welfare, or payment channels are implemented. Those future modules must
not invent independent balance logic or mutate wallets.

## Decision

Use one organisation-isolated, double-entry ledger in the Accounting domain.
All posted financial effects pass through `PostFinancialTransaction`, which
atomically validates authorization, active context, accounts, branch ancestry,
currency, positive entries, debit/credit equality, accounting date, and an
organisation-scoped idempotency key.

Wallets are identities for a member, organisation, branch, or collector and map
to a controlling ledger account. They have lifecycle state but no stored
balance. Balance and trial-balance queries aggregate immutable posted entries.
Posted transactions and entries cannot be edited or deleted; correction is an
exact compensating reversal linked to the original.

Each organisation currently operates in its configured single currency.
System chart accounts are organisation-wide; branch context may post against
them while the transaction records its branch. If no accounting periods have
been configured, posting is implicitly open; after adoption, only open or
reopened periods accept postings.

Contribution obligations remain schedules in Contributions. They are neither
ledger entries nor wallet balances.

## Consequences

- Future financial modules must supply balanced entry instructions and a
  stable reference/idempotency key to Accounting.
- Stored balance drift is eliminated; aggregation can later be optimized with
  rebuildable projections without changing the source of truth.
- The default chart, period policy, approval thresholds, and future
  multi-currency behavior still require accounting-owner review.
- Production-engine CI is required for concurrency behavior around number
  allocation, idempotency, and active-wallet uniqueness.
