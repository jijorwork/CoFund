# CoFund Product Requirements

## Expense and petty-cash foundation

CoFund supports classified, evidenced expense requests with explicit review,
approval, direct payment, reversal, and completion. Petty-cash advances remain
staff receivables until supported retirement and returned cash clear them;
shortages are not automatically expensed.

## Cash-control foundation

Collector cash is grouped into explicit collection sessions. A collector
closes a session, declares physical cash, and submits it for independent
review. Approved handovers move accepted cash from collector to office
custody; shortages and overages remain traceable. Independently confirmed
deposit batches then move office cash to bank or manual Mobile Money custody.
All balances come from the central double-entry ledger.

## Product intent

CoFund is an API-first, multi-organisation community-finance platform for susu, welfare associations, cooperatives, savings groups, and related organisations. The primary experience must remain simple and mobile-friendly while exposing stable capabilities under `/api/v1` for first-party and external clients.

## Confirmed requirements

- One person has one user account. A member record is separate from a login, and a person may be a member of multiple organisations.
- Membership includes organisation-specific numbers, explicit lifecycle history, optional account linkage, groups, beneficiaries, next of kin, emergency contacts, and private supporting documents.
- Organisations may contain multiple branches and groups.
- A user may have multiple role assignments, differing by organisation and branch, and may switch active organisation, branch, role, and optionally group without signing out.
- Every organisation-owned record is tenant-isolated. Active organisation context comes only from a verified assignment, never arbitrary request data.
- Financial transactions use a central double-entry ledger. Wallet balances are projections and must never be changed directly.
- Completed financial transactions are retained. Corrections use linked compensating transactions, not edits or deletion.
- Sensitive actions support configurable approval and audit trails.
- The roadmap includes contributions, welfare, loans, guarantors, expenses, petty cash, reconciliation, complaints, beneficiaries, receipts, statements, Mobile Money, reminders, backups, and external APIs.

## MVP scope

The first MVP establishes organisations, access context, members and beneficiaries, contribution plans and enrolments, the ledger and wallets, contribution collection, expenses, reconciliation, approvals, auditing, administration, and a hardened versioned API.

The first MVP originally deferred loans, Mobile Money, rotational payouts, offline collection, and advanced reporting. Phase 09 now activates the loan domain as the first post-foundation lifecycle; the other deferred capabilities remain out of scope, and loans must not depend on them.

The contribution-plan foundation supports regular, target, welfare, flexible,
and rotational-susu definitions; rotational payout ordering and execution stay
deferred. Fixed, member-selected, and flexible amount rules, enrolment approval,
expected schedules, and staff position summaries are supported. Expected
obligations remain planned contributions only. Manual contribution collection,
allocation, receipts, wallet credit, and ledger accounting are now supported;
provider integrations and reconciliation remain later phases.

## Product principles

1. Protect member funds and traceability before convenience.
2. Make the common mobile workflow short and comprehensible.
3. Keep domain rules consistent across API and administration interfaces.
4. Prefer explicit approval, posting, reversal, and reconciliation states.
5. Avoid exposing internal numeric identifiers or accepting tenant ownership from clients.

## Success criteria for the foundation

- Cross-tenant access is denied by construction and tested.
- Every posted financial event balances and can be traced to its business source.
- Retried write requests do not duplicate financial effects.
- An authenticated user can safely select among only their active assignments.
- The same domain actions serve the API and future administration UI.
## Phase 10 welfare scope

Welfare products support member enrolment, configurable waiting periods and
eligibility, welfare events, claims, beneficiaries, evidence verification,
maker-checker approvals, accrued claim liabilities, partial or full benefit
payments, receipts, reversals, and operational reports. Calculations use minor
units and basis points. External provider integrations, automated medical
verification, payout callbacks, notifications, and payroll deductions remain
deferred.
