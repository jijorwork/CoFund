# Module Status

“No UI” includes administration UI. Partial means only the explicitly described
foundation exists; it does not imply the module's later workflows are available.

| Module | Status | Migrations | Models | API | UI | Tests |
| --- | --- | --- | --- | --- | --- | --- |
| Identity roles, permissions, and active context | Foundation complete | Roles, permissions, role-permission links, organisation role assignments, user public IDs | Role and organisation role assignment | Context selection, roles, role assignments | No UI | Seeder, domain, context, API, and isolation tests |
| Organisations | Foundation complete | Organisations, branches, settings | Organisation, branch, setting | Health/context only; no CRUD | No UI | Domain and isolation tests |
| Membership | Filament Stage 3 implemented | Members, number sequences, status history, groups, group memberships, contacts, documents | Member, group, contact, document and history models | Member lifecycle, groups, contacts, account links, private documents | Scoped registration, profile and lifecycle actions, contacts and beneficiaries, groups, private documents, account links, and read-only wallet, contribution, and loan summaries | Domain, API, privacy, scope, lifecycle, storage, direct-URL authorization, workflow delegation, responsive UI, branch containment, and financial-summary tests |
| Contributions and collection | Collection foundation complete | Plans, enrolments, split-status obligations, payments, allocations, payment sequences | ContributionPlan, MemberPlanEnrolment, ContributionObligation, ContributionPayment, ContributionPaymentAllocation | Planning, enrolment, collection, allocation, reversal, member payment history and position | No UI | Domain, scheduling, posting, allocation, reversal, API, authorization and isolation tests |
| Wallets | Foundation complete | Wallets with polymorphic owners and active-owner uniqueness | Wallet | Staff CRUD/lifecycle, derived balance, member wallet summary | No UI | Domain, API, lifecycle, balance, authorization, and isolation tests |
| Savings withdrawals | Phase 11 implemented | Products, reasons, holds, requests, terms, approvals, payouts, amendments and sequences | Withdrawal domain models/actions/services | `/api/v1` configuration, holds, calculation, request workflow, payouts and reports | No UI | Focused foundation, fee, route and accounting tests added |
| Accounting | Foundation complete | Chart of accounts, transaction sequence, transactions, entries, periods, idempotency keys | LedgerAccount, FinancialTransaction, TransactionEntry, AccountingPeriod | Account CRUD/lifecycle, controlled posting, reversal, transaction views, trial balance | No UI | Posting, balancing, idempotency, reversal, immutability, periods, API, authorization, and isolation tests |
| Loans | Phase 09 implemented | Migrations added | Domain models/actions/services added | `/api/v1` lifecycle and portfolio API added | No UI | Focused domain/API tests added |
| Welfare | Phase 10 implemented | Products, event types, enrolments, events, claims, terms, beneficiaries, approvals, documents, liabilities, payments, amendments and sequences | Welfare domain models, actions and calculation/eligibility services | `/api/v1` product, enrolment, event, claim, beneficiary, document, liability, payment and report endpoints | No UI | Focused domain, API, accounting, authorization and isolation tests added |
| Expenses and petty cash | Foundation implemented | Categories, requests, items, approvals, documents, payments, advances, retirements, sequences | Expense and petty-cash models | Categories, requests, approvals, payments, evidence, advances, retirements and reports | No UI | Foundation, route, authentication and accounting regression tests |
| Reconciliation | Cash-control foundation complete | Sessions, declarations, handovers, variances, deposits, sequences, payment-session link | CollectorCollectionSession, CollectorCashDeclaration, CollectorCashHandover, CollectorCashVariance, CashDepositBatch | Session, handover, variance, deposit lifecycle and operational reports | No UI | Domain, posting, authorization, idempotency, isolation and API tests |
| Reports and dashboards | Phase 12 foundation implemented | Saved reports, report exports, report runs | SavedReport, ReportExport, ReportRun and report query services | Tenant/branch dashboards, ledger financial statements, saved reports and private CSV exports | No UI | Registry, ratio, ageing, schema, route and authentication tests added |
| Notifications | Phase 13 foundation implemented | Policies, preferences, versioned templates, dispatches, deliveries, immutable attempts, in-app notifications and digest foundations | Notification registry, safe renderer, dispatch action and provider-neutral channel services | Own-notification read/unread/archive/summary and own-preference endpoints | No UI | Registry and safe-rendering unit coverage added; full suite pending user execution |
| Approvals | Planned | No migrations | No models | No API | No UI | No tests |
| Complaints | Planned | No migrations | No models | No API | No UI | No tests |
| Payments and Mobile Money | On hold | No provider-integration migrations | No provider-integration models | Manual records only; no provider callbacks | No provider UI | No provider-integration tests |
| Auditing | Member lifecycle foundation | Append-only tenant-scoped audit events | Immutable AuditEvent | No API | No UI | Actor, role, subject, immutability, and transaction rollback tests |
| Beneficiaries | Foundation complete within Membership | Unified member contacts | MemberContact | Nested contact API and entitlement validation | No UI | Allocation, nesting and masking tests |
| Receipts and statements | Digital receipt foundation complete | Receipts and receipt-number sequences | Receipt | Staff list/detail/print data/reissue and public verification; statements deferred | No UI | Issuance, reversal, verification, privacy and API tests |
| Reminders | Planned | No migrations | No models | No API | No UI | No tests |
| Backups | Planned | No migrations | No models | No API | No UI | No tests |
| External APIs | Planned | No migrations | No models | No API | No UI | No tests |
| Administration | Filament Stage 3 membership work in progress | Organisation-access public UUID, grant-actor link, and append-only access-status history added | Existing organisation, access, role-assignment, branch, member, accounting, and notification models plus access-status history | Existing API remains authoritative | Secure panel, persistent context, Stage 2 tenant administration, and first Stage 3 member lifecycle/detail slice; organisation creation and branch mutations remain deferred | Panel and action permissions, workflow delegation, public-UUID direct URLs, tenant/branch scope, responsive tables, masked personal data, eager loading, lifecycle history, platform-role exclusion, self-lockout prevention, and financial immutability coverage |
| API documentation and hardening | Phase 15 foundation implemented | No migrations | No models | Generated OpenAPI 3.1 contract and route inventory; global v1 limiter and standard routing errors | No UI | Route authentication/context contract, generated coverage, request-ID and security-header tests |

The starter repository already contains global Fortify authentication. The
identity foundation now adds system roles, a focused permission catalogue,
organisation-aware assignments, and session-persisted active context. Custom
roles, direct user permissions, groups, token-specific contexts, and platform
administration remain deferred.

Contribution collection now supports manually confirmed cash, bank, cheque,
card, and Mobile Money records through the central ledger. Provider callbacks,
cash/bank reconciliation, rendered PDFs, rotational payouts, and member
self-service remain deferred.
