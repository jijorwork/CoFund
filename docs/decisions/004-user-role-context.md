# ADR 004: User Role Context

- **Status:** Accepted
- **Date:** 2026-07-25

## Context

One user can hold different roles across organisations and branches and must switch work context without signing out. A member is not necessarily a login and must remain separate.

## Decision

Keep users global and model role assignments separately. Each tenant request resolves `active_organisation_id`, `active_branch_id`, `active_role_id`, and optional `active_group_id` into an immutable context after verifying the authenticated user's active, valid assignment and ownership ancestry. Authorization combines that assignment's capabilities with resource policies.

## Consequences

Revocation can take effect without account changes, and one person retains one login. Context transport differs by authentication channel and must be selected before the API is implemented. Context switching and stale assignment behavior require first-class tests and auditing.

