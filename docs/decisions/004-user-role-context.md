# ADR 004: User Role Context

- **Status:** Accepted
- **Date:** 2026-07-25

## Context

One user can hold different roles across organisations and branches and must switch work context without signing out. A member is not necessarily a login and must remain separate.

## Decision

Keep users global and model role assignments separately in
`organisation_user_roles`. Spatie's global roles and permissions define the
capability catalogue but its polymorphic model assignments are not CoFund's
tenant authority. Each tenant request resolves `active_organisation_id`,
`active_branch_id`, `active_role_id`, and the exact assignment into scoped
context after verifying the authenticated user's active access, assignment
validity, and ownership ancestry. Optional group context remains deferred.
Authorization evaluates only the selected role's capabilities plus resource
policy.

## Consequences

Revocation takes effect without account changes, one person retains one login,
and switching roles cannot combine permissions. Assignment history is retained.
The current browser channel uses server-side sessions as recorded in ADR 005;
future token contexts require a separate decision.
