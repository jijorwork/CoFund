# ADR 005: Active Context Persistence

- **Status:** Accepted
- **Date:** 2026-07-25

## Context

CoFund currently authenticates first-party requests with Laravel Fortify's
server-side `web` session. A user must explicitly select an exact organisation,
optional branch, and role assignment without signing out.

## Decision

Persist only their public UUID selectors in the authenticated server-side
session. Context listing and switching require authentication but no existing
role context. Staff requests run session middleware, authentication, tenant
resolution, then role resolution. Both resolvers reload current records and
verify active organisation access, branch ancestry/status, assignment ownership,
status, and validity dates. Any stale or invalid selection is cleared and
returns the generic `context_invalid` response.

Authorization uses only the resolved active role. Other assignments held by the
same user do not contribute permissions.

## Consequences

No `user_contexts` table or custom token format is needed. Multiple browser
sessions maintain independent context through their session identifiers.
Revocation, suspension, expiry, or organisation/branch deactivation takes effect
on the next protected request. Future API tokens, devices, and external clients
need a separate context-persistence decision and must not share this browser
session assumption.
