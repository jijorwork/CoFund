# Module Status

“No UI” includes administration UI. Partial means only the explicitly described
foundation exists; it does not imply the module's later workflows are available.

| Module | Status | Migrations | Models | API | UI | Tests |
| --- | --- | --- | --- | --- | --- | --- |
| Identity roles, permissions, and active context | Foundation complete | Roles, permissions, role-permission links, organisation role assignments, user public IDs | Role and organisation role assignment | Context selection, roles, role assignments | No UI | Seeder, domain, context, API, and isolation tests |
| Organisations | Foundation complete | Organisations, branches, settings | Organisation, branch, setting | Health/context only; no CRUD | No UI | Domain and isolation tests |
| Membership | Foundation complete | Members, number sequences, status history, groups, group memberships, contacts, documents | Member, group, contact, document and history models | Member lifecycle, groups, contacts, account links, private documents | No UI | Domain, API, privacy, scope, lifecycle and storage tests |
| Contributions | Plan and enrolment foundation complete | Plans, enrolments, expected obligations | ContributionPlan, MemberPlanEnrolment, ContributionObligation | Plan lifecycle, enrolment lifecycle, schedules, staff member summary | No UI | Domain, scheduling, API, authorization and isolation tests |
| Wallets | Planned | No migrations | No models | No API | No UI | No tests |
| Accounting | Planned | No migrations | No models | No API | No UI | No tests |
| Loans | Planned | No migrations | No models | No API | No UI | No tests |
| Welfare | Planned | No migrations | No models | No API | No UI | No tests |
| Expenses and petty cash | Planned | No migrations | No models | No API | No UI | No tests |
| Reconciliation | Planned | No migrations | No models | No API | No UI | No tests |
| Approvals | Planned | No migrations | No models | No API | No UI | No tests |
| Complaints | Planned | No migrations | No models | No API | No UI | No tests |
| Payments and Mobile Money | Planned | No migrations | No models | No API | No UI | No tests |
| Auditing | Planned | No migrations | No models | No API | No UI | No tests |
| Beneficiaries | Foundation complete within Membership | Unified member contacts | MemberContact | Nested contact API and entitlement validation | No UI | Allocation, nesting and masking tests |
| Receipts and statements | Planned | No migrations | No models | No API | No UI | No tests |
| Reminders | Planned | No migrations | No models | No API | No UI | No tests |
| Backups | Planned | No migrations | No models | No API | No UI | No tests |
| External APIs | Planned | No migrations | No models | No API | No UI | No tests |
| Administration | Planned | No migrations | No models | No API | No UI | No tests |

The starter repository already contains global Fortify authentication. The
identity foundation now adds system roles, a focused permission catalogue,
organisation-aware assignments, and session-persisted active context. Custom
roles, direct user permissions, groups, token-specific contexts, and platform
administration remain deferred.

The Contributions foundation defines expected contribution obligations only.
Collection, allocation, receipts, wallets, ledger postings, rotational payouts,
and member self-service remain deferred.
