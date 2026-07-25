# ADR 002: Multi-organisation Tenancy

- **Status:** Accepted
- **Date:** 2026-07-25

## Context

Users may work in multiple organisations, branches, groups, and roles during one login. Arbitrary request tenant IDs create an unacceptable cross-tenant risk.

## Decision

Use shared-schema, row-level tenancy. Every tenant-owned record carries `organisation_id` plus branch/group ownership where applicable. Middleware resolves an active context from public selectors and accepts it only when an active assignment belonging to the authenticated user matches the complete ancestry. Actions stamp ownership from that verified context; scoped queries and policies enforce it again.

## Consequences

The model is operationally simple and supports atomic ledger workflows. Every query, job, cache, file, export, and uniqueness rule must be tenant-aware. Production-engine constraints and automated isolation tests are mandatory defenses.

