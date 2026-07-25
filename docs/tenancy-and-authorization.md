# Tenancy and Authorization

## Identity model

`User` is a global login. A person/member profile is a separate domain record and may participate in multiple organisations. Authorization comes from active role assignments, not from a role column on users.

A role assignment relates a user to an organisation, a role, and optionally a branch (and, only if needed, a group). It has an active state and validity dates. Roles describe capabilities; assignments describe where a user may exercise them.

## Organisation-foundation access

Until roles are implemented, `organisation_users` is a deliberately limited
access/affiliation record containing organisation, user, status, and optional
join time. It contains no role or permission. Tenant middleware accepts only an
active access record and active organisation. This is an interim authorization
gate, not the final role model.

The current request-scoped `TenantContext` contains an organisation and optional
branch. HTTP clients select them with `X-Organisation-ID` and optional
`X-Branch-ID`, both carrying `public_id` UUIDs. Missing, unknown, inactive,
cross-organisation, and inaccessible selectors all return the same
`403 context_invalid` response. The service never selects a default tenant and
throws `TenantContextMissing` when accessed before resolution.

## Active context

The request-scoped context contains:

- `active_organisation_id` — required for tenant-owned operations.
- `active_branch_id` — nullable only for organisation-wide assignments/actions.
- `active_role_id` — required and must identify the selected assignment/role, not merely a role name.
- `active_group_id` — optional and must belong to the selected branch/organisation.

Only the first two values are implemented in the organisation foundation.
`active_role_id`, role validation, and group context remain planned for the next
phases.

Clients select context using public identifiers. A context-switch operation must query an assignment belonging to the authenticated user and verify: assignment is active and within validity dates; organisation and branch are active; assignment organisation matches the selected organisation; branch-scoped roles match the selected branch; organisation-wide roles may select only an allowed branch; selected group belongs to that branch/organisation; and the role remains enabled.

On success, persist only a selector suitable for the authentication channel (for example, server session for the current web UI or a short-lived signed context claim/token for a future API). Every request re-resolves or safely caches the assignment and produces an immutable `ActiveContext`. A client-supplied tenant ID is a selector, never trusted ownership.

If an assignment is revoked, expired, or moved, context validation fails immediately with a safe `403 context_invalid`; the client must choose another valid context. Switching context does not sign the user out.

## Enforcement

- Creation actions stamp `organisation_id` and applicable branch/group IDs from `ActiveContext`.
- Queries scope resources to context before route binding or lookup; policies additionally authorize the operation and role capability.
- Nested resource ancestry is validated. A matching numeric ID without matching organisation is never sufficient.
- Organisation-wide support or platform administration must be explicit, audited, least-privilege functionality; it must not be an unscoped bypass.
- Jobs, scheduled work, exports, and commands receive an explicit organisation and actor/system identity. They do not read a browser session.
- Cache keys, idempotency records, files, logs, notifications, search indexes, and exports include tenant boundaries.

## Audit metadata

Sensitive actions capture request ID, organisation/branch/group, role assignment, actor user, impersonator/support actor if any, action, subject type/public ID, timestamp, source channel, IP/user agent where appropriate, reason, approval reference, and before/after changes with secrets redacted. Audit events are append-only and access-controlled.

## Owner decisions

Confirm whether roles are organisation-defined or platform templates, whether one assignment can cover all branches, how group-level permissions behave, the context transport for API clients, and whether platform-support impersonation is permitted.
