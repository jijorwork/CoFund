# ADR 007: Bounded Contribution Schedule Obligations

Status: Accepted  
Date: 2026-07-25

## Context

CoFund needs stable expected-contribution dates without treating an expectation
as cash, a wallet balance, or an accounting transaction. Generating an
enrolment's entire lifetime would create unbounded data; calculating every date
only at read time would make later arrears and allocation queries unstable.

## Decision

Persist `contribution_obligations` as expected instalments. Generate a bounded,
configurable horizon when an enrolment becomes active: 60 daily dates, 12
weekly dates, 12 monthly dates, one date for a one-time plan, and no automatic
dates for a flexible plan. Use organisation-local calendar dates, ISO weekday
numbers 1–7, monthly days 1–28, and grace date equal to due date plus configured
days.

Generation locks the enrolment, is transactional, and is idempotent through
enrolment/due-date and enrolment/sequence uniqueness. An explicit extension
action fills missing obligations to the current horizon. Each obligation copies
the expected minor-unit amount in effect when generated.

Schedule-critical plan fields cannot change while pending, active, or suspended
enrolments exist. A future plan-versioning feature is required to change those
terms without rewriting history.

## Consequences

A later operational scheduler must extend active enrolments regularly. Ending
an enrolment cancels only obligations after its effective date and preserves all
records. No obligation is a payment, ledger posting, receipt, wallet balance,
debt balance, or evidence that money was received.
