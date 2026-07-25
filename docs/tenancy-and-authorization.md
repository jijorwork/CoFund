# Tenancy and Authorization

## Identity model

`User` is a global login. A person/member profile is a separate domain record and may participate in multiple organisations. Authorization comes from active role assignments, not from a role column on users.

A role assignment relates a user to an organisation, a role, and optionally a branch (and, only if needed, a group). It has an active state and validity dates. Roles describe capabilities; assignments describe where a user may exercise them.

## Organisation access

`organisation_users` is a deliberately limited access/affiliation record
containing organisation, user, status, and optional join time. It contains no
role or permission. Active access is required both to receive and to use an
organisation role assignment.

The request-scoped `TenantContext` contains an organisation, optional branch,
active role, and exact organisation role assignment. Missing, unknown,
inactive, cross-organisation, and inaccessible selectors all return the same
`403 context_invalid` response. No default organisation or first available role
is selected.

## Active context

The request-scoped context contains:

- `active_organisation_id` — required for tenant-owned operations.
- `active_branch_id` — nullable only for organisation-wide assignments/actions.
- `active_role_id` — required and must identify the selected assignment/role, not merely a role name.
- `active_group_id` — optional and must belong to the selected branch/organisation.

The organisation, branch, role, and exact role assignment are implemented.
Group context remains deferred until groups exist.

Clients select context using public identifiers. A context-switch operation must query an assignment belonging to the authenticated user and verify: assignment is active and within validity dates; organisation and branch are active; assignment organisation matches the selected organisation; branch-scoped roles match the selected branch; organisation-wide roles may select only an allowed branch; selected group belongs to that branch/organisation; and the role remains enabled.

For the current Fortify browser-authenticated channel, successful selection
stores organisation, optional branch, and role-assignment public UUIDs in the
server-side session. Every protected request reloads and revalidates the
assignment. Session values are selectors, never proof of ownership.

If an assignment is revoked, expired, or moved, context validation fails immediately with a safe `403 context_invalid`; the client must choose another valid context. Switching context does not sign the user out.

## Enforcement

- Creation actions stamp `organisation_id` and applicable branch/group IDs from `ActiveContext`.
- Queries scope resources to context before route binding or lookup; policies additionally authorize the operation and role capability.
- Nested resource ancestry is validated. A matching numeric ID without matching organisation is never sufficient.
- Organisation-wide support or platform administration must be explicit, audited, least-privilege functionality; it must not be an unscoped bypass.
- Permission checks use only the active assignment's role. Other roles held by the same user grant nothing until explicitly selected.
- Jobs, scheduled work, exports, and commands receive an explicit organisation and actor/system identity. They do not read a browser session.
- Cache keys, idempotency records, files, logs, notifications, search indexes, and exports include tenant boundaries.

Membership resources apply an additional reusable branch scope. A
branch-specific assignment sees and mutates only records assigned to its active
branch; it cannot reach branchless organisation-wide members or groups.
Organisation-wide assignments may operate across branches when their selected
role has the required permission. Member-to-user linking requires active
`organisation_users` access and does not grant a role.

Contribution plans apply tenant ancestry and plan availability scope. A branch
role may read organisation-wide plans and plans for its active branch, but may
create only plans scoped to that branch. Enrolments, schedules, and member
contribution summaries require an exact active-branch match. Group-specific
plans additionally require compatible branch ancestry and the member's active
membership in that group.

## Audit metadata

Sensitive actions capture request ID, organisation/branch/group, role assignment, actor user, impersonator/support actor if any, action, subject type/public ID, timestamp, source channel, IP/user agent where appropriate, reason, approval reference, and before/after changes with secrets redacted. Audit events are append-only and access-controlled.

## Deferred owner decisions

Confirm how group-level permissions behave, which token authentication mechanism
future third-party/mobile clients use, and whether audited platform-support
impersonation is permitted. System roles are platform templates for this phase;
organisation-defined roles remain deferred.
