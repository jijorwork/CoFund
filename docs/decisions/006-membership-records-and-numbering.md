# ADR 006: Membership Records and Numbering

- **Status:** Accepted
- **Date:** 2026-07-25

## Context

Members may exist without logins, one user may be a member of several
organisations, membership numbers cannot be reused, and contact/document data
requires strict tenant and privacy boundaries.

## Decision

Store one member record per organisation membership, optionally linked to a
global user. Generate `{ORGCODE}-{YYYY}-{000001}` numbers from a
per-organisation/year database counter locked in the member-creation
transaction. Record every status transition in append-only history.

Represent beneficiary, next-of-kin, and emergency-contact records in one typed
contact table. Validate beneficiary allocations transactionally using integer
basis points. Store membership documents on a private Laravel disk using
generated names and an enforced morph map limited to members and member
contacts.

## Consequences

Numbers remain stable after exit or deletion and independent across
organisations. Contact capabilities can evolve without duplicating identity
fields, but type-specific validation is mandatory. Document download always
passes through authorization. Production-engine concurrency tests remain a
release requirement because SQLite does not prove row-lock behavior.
