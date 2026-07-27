# Financial Ledger Design

## Expense and petty-cash postings

Direct payment debits one or more active expense accounts and credits the
source asset wallet. The MVP uses the same one-step posting for reimbursement.
Petty-cash disbursement debits staff cash advance receivable and credits petty
cash. Retirement debits accepted expenses and returned petty cash, and credits
the receivable only for the cleared amount. Shortages remain receivable.

## Invariant

The Accounting domain is the single source of truth for financial balances. Every completed financial event posts one immutable journal containing at least two lines, and total debits equal total credits for each currency. Wallets and account balances are derived projections; no feature may increment or decrement a stored balance directly.

Contribution obligations are scheduled expectations outside the ledger.
Creating or extending a schedule does not post a journal, create a wallet, or
prove receipt of funds. A later allocation service may satisfy an obligation
only from a real payment backed by its accounting posting.

The collection foundation supplies that bridge. One contribution payment posts
one debit to an authorised office-cash, collector-cash, bank, or manual-Mobile-
Money asset wallet and one credit to the member savings liability wallet.
Allocations explain satisfaction of zero or more obligations without creating
additional financial transactions.

## Implemented foundation

The foundation now provides organisation-owned ledger accounts, immutable
financial transactions and entries, accounting periods, transaction-number
sequences, financial idempotency records, and wallets. The default chart is
created explicitly per organisation through `CreateDefaultChartOfAccounts`;
it is not silently created by a model event.

Transaction numbers have the form `{ORGCODE}-TXN-{YEAR}-{SEQUENCE}` and are
allocated under a locked organisation/year sequence. The central
`PostFinancialTransaction` service is the only supported write path for posted
entries. It validates tenant and branch ancestry, active direct-posting
accounts, wallet/account/member consistency, positive integer amounts, one
organisation currency, debit/credit equality, posting period, authorization,
and idempotency in one database transaction.

The default account codes are a system template and not an accounting-policy
endorsement: cash and equivalents 1000 (non-posting), office cash 1100,
collector cash 1200, bank 1300, mobile-money clearing 1400, payment suspense
1500, member savings liability 2000, member welfare liability 2100, member
refunds payable 2200, opening-balance equity 3000, retained surplus 3100,
administrative-fee income 4000, penalty income 4100, other income 4200,
operating expenses 5000, transaction charges 5100, and other expenses 5200.
No loan, welfare-claim, or petty-cash workflow accounts are created.

## Core concepts

- **Ledger account:** organisation-owned chart-of-accounts node with type (asset, liability, equity, income, expense) and currency policy.
- **Financial transaction:** immutable posted header with organisation, branch where applicable, reference type/public ID, accounting date, currency, description, idempotency key/fingerprint, actors, and audit metadata.
- **Transaction entry:** a debit or credit positive minor-unit amount against one account. Debit and credit totals must match.
- **Wallet:** a domain-facing identity mapped to a controlling ledger account; its available/book balance is calculated from posted entries or a rebuildable projection.
- **Business transaction:** contribution, expense, welfare payment, reconciliation adjustment, and later repayment/payment. It requests a posting through Accounting rather than writing entries itself.

## Posting lifecycle

1. Validate tenant context, authorization, business state, currency, and idempotency.
2. Resolve the configured posting rule and accounts.
3. Build all journal lines in memory and assert balance/non-zero amounts.
4. In one database transaction, lock relevant state, allocate a number, persist the transaction and entries, mark it posted, and complete the idempotency record.
5. Update or enqueue rebuildable projections only after the immutable source exists.

Draft business records do not affect balances. Approval and posting are separate states when maker-checker control applies. A failed posting leaves neither a completed business transaction nor partial entries.

## Reversals and corrections

Posted journals and completed source transactions are never edited or deleted. A reversal creates a new journal with debit/credit lines inverted, references the original journal, records reason/actor/approval, and prevents accidental duplicate full reversal. A correction is a reversal followed by a new correct business transaction. Both remain visible in statements and audits.

The implemented reversal date is the current date in the organisation time
zone. It must fall in an open or reopened period. Reversals of reversals and
second full reversals are rejected.

A contribution-payment reversal reverses its ledger transaction, marks its
active allocations reversed, and recalculates affected obligations. Original
payments, entries, allocations, and receipts remain available; linked reversal
payment and receipt records receive new numbers.

## Balances and boundaries

Ledger-account, wallet, member-wallet, and trial-balance results are derived
from posted entries. No balance column is stored. The member wallet summary is
deliberately separate from contribution obligations: an expected contribution
is not cash, a wallet balance, or accounting evidence.

Unallocated contribution balance comes from active original payment records,
not wallet balance. Both allocated and unallocated cash increase member savings
because both are liabilities owed to the member.

When an organisation has no accounting-period records, posting is implicitly
open to allow staged adoption. Once any period exists, only an explicit open or
reopened period containing the accounting date accepts a posting. Period
creation, close, and reasoned reopen actions exist at the domain layer; their
administrative API/UI is deferred.

## Controls

- Enforce balanced journals in the posting service and database-compatible constraints where practical.
- Disallow cross-organisation lines and validate branch ancestry.
- Use integer minor units and prohibit float inputs.
- Keep explicit posting periods/effective dates; closed-period behavior requires accounting-owner approval.
- Reconciliation marks external evidence against posted entries; it does not rewrite them.
- Provide a deterministic projection rebuild and periodic checks that wallet projections equal ledger totals.

## Collection reconciliation

Collector-cash payments must belong to the collector wallet's single open
collection session. Closing freezes its payment population and stores a
rebuildable expected-cash snapshot; authoritative balances remain derived from
posted journals.

An approved handover debits accepted cash into office cash and credits
collector cash. A shortage transfers only the accepted amount, leaving the
missing amount in collector custody until repayment or approved write-off. An
overage also credits the unidentified-cash-overage liability. A confirmed
deposit debits bank or Mobile Money and credits office cash. Reversals use
compensating journals and never edit posted entries.

Default chart accounts 2300 (unidentified cash overage liability) and 5300
(collector cash shortage expense) support these controls.

## Loan accounting

Phase 09 adds member loan receivables and distinct loan interest, processing-fee,
and penalty income accounts. Loan applications, eligibility decisions,
guarantees, collateral, approvals, and offers remain non-accounting records.
Disbursement and repayment accounting use the central posting service.

Loan income recognition follows a cash policy for contractual interest,
repayment fees, and penalties. Processing fees are recognised once at
disbursement. Reversed disbursements and repayments use exact compensating
transactions and never rewrite the original journal.

## Welfare accounting

Final welfare approval recognises the approved obligation using accrual
accounting: debit welfare benefit expense (5400) and credit welfare claims
payable (2500). A benefit payment debits welfare claims payable and credits the
selected active asset-wallet account. Partial payments reduce the outstanding
payable without editing the liability. Liability and payment reversals create
exact compensating transactions; active payments must be reversed before their
liability can be reversed. Eligibility evaluation, submission, review, and
verification do not create financial transactions.

## Savings withdrawal accounting

A withdrawal request, review, reservation, and approval are non-accounting
events. A posted payout debits the member savings liability for the gross
withdrawal. It credits the destination asset wallet for the net payout and
credits withdrawal fee income for any fee deducted. Partial payouts allocate
the approved fee proportionally; the final payout absorbs integer-rounding
residuals. Reversals use the central compensating-transaction service and
restore the member savings liability.

## Decisions still required

Owner/accounting review is required for approval of the initial chart, whether
to require periods from day one, cash-versus-accrual policy, future
multi-currency and precision, negative-wallet/overdraft rules, approval
thresholds, opening-balance workflow, and exact postings for each future
business transaction type.
