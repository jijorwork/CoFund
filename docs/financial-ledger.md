# Financial Ledger Design

## Invariant

The Accounting domain is the single source of truth for financial balances. Every completed financial event posts one immutable journal containing at least two lines, and total debits equal total credits for each currency. Wallets and account balances are derived projections; no feature may increment or decrement a stored balance directly.

Contribution obligations are scheduled expectations outside the ledger.
Creating or extending a schedule does not post a journal, create a wallet, or
prove receipt of funds. A later allocation service may satisfy an obligation
only from a real payment backed by its accounting posting.

## Core concepts

- **Ledger account:** organisation-owned chart-of-accounts node with type (asset, liability, equity, income, expense) and currency policy.
- **Journal:** immutable posting header with organisation, branch where applicable, business source type/public ID, effective date, currency, description, posting status, idempotency reference, and audit metadata.
- **Journal line:** debit or credit minor-unit amount against one account. A line has exactly one positive side, never both.
- **Wallet:** a domain-facing identity mapped to a controlling ledger account; its available/book balance is calculated from posted entries or a rebuildable projection.
- **Business transaction:** contribution, expense, welfare payment, reconciliation adjustment, and later repayment/payment. It requests a posting through Accounting rather than writing entries itself.

## Posting lifecycle

1. Validate tenant context, authorization, business state, currency, and idempotency.
2. Resolve the configured posting rule and accounts.
3. Build all journal lines in memory and assert balance/non-zero amounts.
4. In one database transaction, lock relevant state, persist the journal and lines, mark it posted, link the business source, and write the audit event.
5. Update or enqueue rebuildable projections only after the immutable source exists.

Draft business records do not affect balances. Approval and posting are separate states when maker-checker control applies. A failed posting leaves neither a completed business transaction nor partial entries.

## Reversals and corrections

Posted journals and completed source transactions are never edited or deleted. A reversal creates a new journal with debit/credit lines inverted, references the original journal, records reason/actor/approval, and prevents accidental duplicate full reversal. A correction is a reversal followed by a new correct business transaction. Both remain visible in statements and audits.

## Controls

- Enforce balanced journals in the posting service and database-compatible constraints where practical.
- Disallow cross-organisation lines and validate branch ancestry.
- Use integer minor units and prohibit float inputs.
- Keep explicit posting periods/effective dates; closed-period behavior requires accounting-owner approval.
- Reconciliation marks external evidence against posted entries; it does not rewrite them.
- Provide a deterministic projection rebuild and periodic checks that wallet projections equal ledger totals.

## Decisions still required

Owner/accounting review is required for the initial chart of accounts, organisation-level account templates, cash-versus-accrual policy, supported currencies and precision, negative-wallet rules, posting-period close, approval thresholds, and exact postings for every transaction type.
