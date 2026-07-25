# ADR 003: Central Double-entry Financial Ledger

- **Status:** Accepted
- **Date:** 2026-07-25

## Context

Contributions, welfare, expenses, reconciliation, and later loans/payments all affect funds. Independent mutable balances would make audit and correction unreliable.

## Decision

Accounting owns a central, organisation-scoped double-entry ledger. Completed business transactions post balanced immutable journals in the same database transaction. Wallet balances are rebuildable ledger projections and cannot be directly mutated. Corrections use linked compensating journals; completed transactions are never deleted.

## Consequences

All money-changing modules need explicit posting rules and idempotency. Reporting and reconciliation gain one authoritative history. Initial chart of accounts and transaction mappings require accounting-owner approval before implementation.

