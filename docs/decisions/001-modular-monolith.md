# ADR 001: Modular Monolith

- **Status:** Accepted
- **Date:** 2026-07-25

## Context

CoFund spans many related finance domains that require transactional consistency, especially between business workflows, approvals, audits, and accounting.

## Decision

Build one Laravel modular monolith with one deployment and one transactional database. Organise code by domain namespaces inside the application, use actions for use cases, and introduce contracts only at real boundaries such as payment providers. Do not create microservices or package-per-domain boundaries.

## Consequences

Cross-domain transactions and local development remain straightforward. Domain ownership and dependency rules must be protected by tests and review. A future extraction is possible only after observed scaling or organisational needs justify it.

