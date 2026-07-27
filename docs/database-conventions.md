# Database Conventions

## Expense records

Expense tables use organisation ownership, optional branch ownership, public
UUIDs, organisation-scoped numbers/idempotency, integer minor units,
request-local item/approval sequences, restrictive historical foreign keys,
and explicit MySQL-safe key names.

## Reconciliation constraints

Reconciliation uses explicit, MySQL-safe constraint and index names. Session,
handover, deposit, and declaration numbers are unique within an organisation;
handover/deposit idempotency keys are organisation-scoped; nullable unique keys
enforce one active session and declaration. Foreign keys restrict deletion of
cash-custody history.

Cross-column monetary invariants are enforced in domain actions because
SQLite/MySQL check-constraint behavior differs across supported environments.
Non-negative amounts use unsigned integers in MySQL and application validation
everywhere. Database uniqueness remains the final concurrent race guard.

## Current configuration

The repository defaults to SQLite (`DB_CONNECTION=sqlite`); database-backed cache, queue, and session are the local defaults. Laravel configurations for MySQL/MariaDB/PostgreSQL/SQL Server also exist. Tests explicitly use SQLite. Production database engine, collation, backup/restore policy, retention, and high-availability requirements require owner approval before schema work.

## Identifiers

- Use Laravel `bigint` auto-incrementing primary keys for internal joins and storage efficiency.
- Give externally addressable domain records a `public_id` UUID (UUIDv7 preferred for locality), unique and immutable. APIs expose `public_id`, never internal `id`.
- Foreign keys use internal IDs. Do not accept an internal ID from a public client.
- Idempotency keys and provider references are strings with uniqueness scoped to their owner/provider.

## Ownership

- Every tenant-owned table has a non-null `organisation_id` foreign key. Global reference data is the deliberate exception and must be documented.
- Branch-owned records also have non-null `branch_id`; the branch must belong to the same organisation. Where the database cannot express that relation alone, enforce it in domain actions and tests; consider composite constraints after selecting the production engine.
- Group-owned records likewise retain `organisation_id` and validate group ancestry.
- Include `organisation_id` in tenant lookup indexes and uniqueness constraints, for example `unique(organisation_id, code)`.
- Tenant scoping is defense in depth: verified context, policies, scoped queries, and relational constraints. A global scope alone is insufficient.

## Data types

- Store money as signed integer minor units (`amount_minor`) plus ISO 4217 `currency_code`; never float or decimal arithmetic in application code. Each journal balances per currency. Whether organisations may be multi-currency requires owner approval.
- Use backed string PHP enums for finite domain states and explicit database string columns. Do not use engine-native enums; changes then remain deployable and backwards compatible.
- Store timestamps in UTC using Laravel timestamps. Store the organisation's IANA time-zone name separately for presentation and business-date calculation. Financial records retain an explicit `effective_date` where accounting date matters.
- Use `date` for calendar-only values and timestamp columns for instants. Convert only at system boundaries.
- JSON is appropriate for provider payload snapshots and additive metadata, not core searchable relationships.

## Deletion and immutability

- Use soft deletion only for recoverable master data where historical relationships must remain, such as branches, groups, plans, and member profiles. A soft-deleted parent cannot be selected for new activity.
- Do not soft-delete completed transactions, posted journals, journal lines, approvals, or audit events. They are immutable records; correction uses status transitions or compensating records.
- Drafts may be edited or discarded if no financial posting exists and audit requirements are preserved.
- Restrict deletion of referenced master records; avoid cascading deletes across financial history.

The organisation foundation applies restrictive foreign keys from branches,
settings, and access records to organisations. Hard deletion of an organisation
therefore requires deliberate dependent cleanup; normal organisation lifecycle
uses soft deletion. `organisation_users.user_id` cascades only when a global user
is hard-deleted because the access link has no independent business meaning.
Future financial foreign keys must remain restrictive.

## Naming

- Tables: plural `snake_case`; models: singular TitleCase; pivot tables: singular model names alphabetically ordered.
- Columns and foreign keys: `snake_case`, with `{model}_id`; public UUID column: `public_id`.
- Indexes use descriptive explicit names when Laravel's generated name risks length/collision: `{table}_{columns}_{index|unique}`.
- Foreign keys use Laravel conventions; when named explicitly: `{table}_{column}_foreign`.
- Status timestamps use past tense (`posted_at`, `approved_at`, `reversed_at`) and actor keys use `{action}_by_user_id` only when the audit trail alone is not sufficient for common queries.

All migrations must be generated with Artisan, focused, reversible where safe,
and accompanied by tests. The organisation foundation currently defines
`organisations`, `branches`, `organisation_settings`, and `organisation_users`.
It uses unique `public_id` values, globally unique organisation codes,
organisation-scoped branch codes and setting keys, and unique
organisation-user pairs.

The membership foundation adds `members`, `membership_number_sequences`,
`member_status_histories`, `groups`, `group_memberships`, `member_contacts`, and
`member_documents`. Membership/group codes are organisation-scoped. Nullable
unique active keys preserve historical rows while preventing duplicate current
user links, group memberships, and primary contacts. Beneficiary percentages
use `decimal(5,2)` and application arithmetic converts them to integer basis
points. Status history is append-only.

Branch ancestry and restricted document morph types are validated in domain
actions because portable relational constraints cannot fully express them.

The contribution foundation adds `contribution_plans`,
`member_plan_enrolments`, and `contribution_obligations`. Plan codes are unique
per organisation. Nullable active-enrolment keys allow historical re-enrolment
while preventing duplicate current enrolments. Obligation sequence numbers and
due dates are unique within an enrolment. Expected amounts are immutable
minor-unit snapshots; ending an enrolment cancels future obligations instead of
deleting history.

The accounting foundation adds `ledger_accounts`,
`transaction_number_sequences`, `financial_transactions`,
`transaction_entries`, `accounting_periods`, and
`financial_idempotency_keys`; Wallets adds `wallets`. Financial history uses
restrictive foreign keys and no soft deletion. Ledger accounts and wallets may
be soft deleted only when they have no entries. Portable unique keys protect
organisation account codes, transaction numbers, reversal links,
idempotency keys, entry sequence, accounting period ranges, and one active
wallet per owner/type/currency. Positive entry amounts, balanced totals, branch
ancestry, and cross-record currency consistency are enforced in the central
posting transaction and tests because portable database constraints cannot
express all of them consistently across SQLite and the unselected production
engine.

Collection adds `contribution_payments`,
`contribution_payment_allocations`, `receipts`,
`payment_number_sequences`, and `receipt_number_sequences`. Payment and receipt
numbers and payment idempotency keys are organisation-scoped unique values.
Allocations and receipts are append-only and corrections use linked records.
Obligation timing now uses `schedule_status`; `satisfaction_status`
independently records unsatisfied, partially satisfied, or satisfied. Active
allocation totals are recalculated under obligation row locks.
## Welfare records

Welfare configuration may be soft-deleted only where history is not rewritten.
Enrolments, events, submitted claims, approvals, liabilities, payments,
amendments, and documents are historical records and are not deleted. Welfare
money is stored as non-negative integer minor units and percentage rates as
basis points. Service-layer transactions enforce aggregate constraints that
cannot be expressed portably, including beneficiary totals, recognised
liability limits, and prevention of overpayment. MySQL check-constraint and
row-lock behaviour must be verified on the production database engine; SQLite
tests do not prove those concurrency guarantees.
